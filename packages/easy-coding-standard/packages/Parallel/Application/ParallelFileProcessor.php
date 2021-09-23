<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Parallel\Application;

use Closure;
use Clue\React\NDJson\Decoder;
use Clue\React\NDJson\Encoder;
use Nette\Utils\Random;
use React\EventLoop\StreamSelectLoop;
use React\Socket\ConnectionInterface;
use React\Socket\TcpServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symplify\EasyCodingStandard\Parallel\Command\WorkerCommandLineFactory;
use Symplify\EasyCodingStandard\Parallel\Enum\Action;
use Symplify\EasyCodingStandard\Parallel\ValueObject\Bridge;
use Symplify\EasyCodingStandard\Parallel\ValueObject\ParallelProcess;
use Symplify\EasyCodingStandard\Parallel\ValueObject\ProcessPool;
use Symplify\EasyCodingStandard\Parallel\ValueObject\ReactCommand;
use Symplify\EasyCodingStandard\Parallel\ValueObject\ReactEvent;
use Symplify\EasyCodingStandard\Parallel\ValueObject\Schedule;
use Symplify\EasyCodingStandard\SniffRunner\ValueObject\Error\CodingStandardError;
use Symplify\EasyCodingStandard\ValueObject\Error\FileDiff;
use Symplify\EasyCodingStandard\ValueObject\Error\SystemError;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\PackageBuilder\Parameter\ParameterProvider;
use Throwable;

/**
 * Inspired from @see
 * https://github.com/phpstan/phpstan-src/commit/9124c66dcc55a222e21b1717ba5f60771f7dda92#diff-39c7a3b0cbb217bbfff96fbb454e6e5e60c74cf92fbb0f9d246b8bebbaad2bb0
 *
 * https://github.com/phpstan/phpstan-src/commit/b84acd2e3eadf66189a64fdbc6dd18ff76323f67#diff-7f625777f1ce5384046df08abffd6c911cfbb1cfc8fcb2bdeaf78f337689e3e2R150
 */
final class ParallelFileProcessor
{
    /**
     * @var int
     */
    public const TIMEOUT_IN_SECONDS = 60;

    private int $systemErrorsCountLimit;

    private ProcessPool|null $processPool = null;

    public function __construct(
        ParameterProvider $parameterProvider,
        private WorkerCommandLineFactory $workerCommandLineFactory
    ) {
        $this->systemErrorsCountLimit = $parameterProvider->provideIntParameter(Option::SYSTEM_ERROR_COUNT_LIMIT);
    }

    /**
     * @param Closure(int): void|null $postFileCallback Used for progress bar jump
     * @return mixed[]
     */
    public function check(
        Schedule $schedule,
        string $mainScript,
        Closure $postFileCallback,
        ?string $projectConfigFile,
        InputInterface $input
    ): array {
        $jobs = array_reverse($schedule->getJobs());
        $streamSelectLoop = new StreamSelectLoop();

        // basic properties setup
        $numberOfProcesses = $schedule->getNumberOfProcesses();

        // initial counters
        $codingStandardErrors = [];
        $fileDiffs = [];
        $systemErrors = [];
        // $systemErrorsCount = 0;

        $reachedSystemErrorsCountLimit = false;

        $tcpServer = new TcpServer('127.0.0.1:0', $streamSelectLoop);
        $this->processPool = new ProcessPool($tcpServer);

        $tcpServer->on(ReactEvent::CONNECTION, function (ConnectionInterface $connection) use (&$jobs): void {
            $inDecoder = new Decoder($connection, true, 512, 0, 4 * 1024 * 1024);
            $outEncoder = new Encoder($connection);

            $inDecoder->on(ReactEvent::DATA, function (array $data) use (&$jobs, $inDecoder, $outEncoder): void {
                $action = $data[ReactCommand::ACTION];
                if ($action !== Action::HELLO) {
                    return;
                }

                $processIdentifier = $data[Option::PARALLEL_IDENTIFIER];
                $parallelProcess = $this->processPool->getProcess($processIdentifier);
                $parallelProcess->bindConnection($inDecoder, $outEncoder);

                if ($jobs === []) {
                    $this->processPool->quitProcess($processIdentifier);
                    return;
                }

                $job = array_pop($jobs);
                $parallelProcess->request([
                    ReactCommand::ACTION => Action::CHECK,
                    'files' => $job,
                ]);
            });
        });

        /** @var string $serverAddress */
        $serverAddress = $tcpServer->getAddress();

        /** @var int $serverPort */
        $serverPort = parse_url($serverAddress, PHP_URL_PORT);

        $systemErrorsCount = 0;

        $reachedSystemErrorsCountLimit = false;

        $handleErrorCallable = static function (Throwable $throwable) use (
            $streamSelectLoop,
            &$systemErrors,
            &$systemErrorsCount,
            &$reachedSystemErrorsCountLimit
        ): void {
            $systemErrors[] = new SystemError($throwable->getLine(), $throwable->getMessage(), $throwable->getFile());

            ++$systemErrorsCount;
            $reachedSystemErrorsCountLimit = true;
            $this->processPool->quitAll();
        };

        for ($i = 0; $i < $numberOfProcesses; ++$i) {
            // nothing else to process, stop now
            if ($jobs === []) {
                break;
            }

            $processIdentifier = Random::generate();
            $workerCommandLine = $this->workerCommandLineFactory->create(
                $mainScript,
                $projectConfigFile,
                $input,
                $processIdentifier,
                $serverPort,
            );

            $parallelProcess = new ParallelProcess($workerCommandLine, $streamSelectLoop, self::TIMEOUT_IN_SECONDS);
            $parallelProcess->start(
                // 1. callable on data
                function (array $json) use (
                    $parallelProcess,
                    &$systemErrors,
                    &$errors,
                    &$fileDiffs,
                    &$codingStandardErrors,
                    &$jobs,
                    $postFileCallback,
                    &$systemErrorsCount,
                    &$reachedInternalErrorsCountLimit,
                    $processIdentifier
                ): void {

                    // decode arrays to objects
                    foreach ($json[Bridge::SYSTEM_ERRORS] as $jsonError) {
                        if (is_string($jsonError)) {
                            $systemErrors[] = sprintf('System error: %s', $jsonError);
                            continue;
                        }

                        $errors[] = CodingStandardError::decode($jsonError);
                    }

                    foreach ($json[Bridge::FILE_DIFFS] as $jsonError) {
                        $fileDiffs[] = FileDiff::decode($jsonError);
                    }

                    foreach ($json[Bridge::CODING_STANDARD_ERRORS] as $jsonError) {
                        $codingStandardErrors[] = CodingStandardError::decode($jsonError);
                    }

                    // @todo why there is a null check?
                    if ($postFileCallback !== null) {
                        $postFileCallback($json[Bridge::FILES_COUNT]);
                    }

                    $systemErrorsCount += $json[Bridge::SYSTEM_ERRORS_COUNT];
                    if ($systemErrorsCount >= $this->systemErrorsCountLimit) {
                        $reachedInternalErrorsCountLimit = true;
                        $this->processPool->quitAll();
                    }

                    if ($jobs === []) {
                        $this->processPool->quitProcess($processIdentifier);
                        return;
                    }

                    $job = array_pop($jobs);
                    $parallelProcess->request([
                        ReactCommand::ACTION => Action::CHECK,
                        'files' => $job,
                    ]);
                },

                // 2. callable on error
                $handleErrorCallable,

                // 3. callable on exit
                function ($exitCode, string $stdErr) use (&$systemErrors, $processIdentifier): void {
                    $this->processPool->tryQuitProcess($processIdentifier);
                    if ($exitCode === Command::SUCCESS) {
                        return;
                    }

                    if ($exitCode === null) {
                        return;
                    }

                    $systemErrors[] = sprintf('Child process error: %s', $stdErr);
                }
            );

            $this->processPool->attachProcess($processIdentifier, $parallelProcess);
        }

        $streamSelectLoop->run();

        if ($reachedSystemErrorsCountLimit) {
            $systemErrors[] = sprintf(
                'Reached system errors count limit of %d, exiting...',
                $this->systemErrorsCountLimit
            );
        }

        return [
            Bridge::CODING_STANDARD_ERRORS => $codingStandardErrors,
            Bridge::FILE_DIFFS => $fileDiffs,
            Bridge::SYSTEM_ERRORS => $systemErrors,
            Bridge::SYSTEM_ERRORS_COUNT => count($systemErrors),
        ];
    }
}
