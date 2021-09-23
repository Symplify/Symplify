<?php

declare(strict_types=1);

namespace Symplify\GitWrapper;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symplify\GitWrapper\Event\GitOutputEvent;
use Symplify\GitWrapper\EventSubscriber\GitLoggerEventSubscriber;
use Symplify\GitWrapper\EventSubscriber\StreamOutputEventSubscriber;
use Symplify\GitWrapper\Exception\GitException;
use Symplify\GitWrapper\Process\GitProcess;
use Symplify\GitWrapper\Strings\GitStrings;

/**
 * A wrapper class around the Git binary.
 *
 * A GitWrapper object contains the necessary context to run Git commands such as the path to the Git binary and
 * environment variables. It also provides helper methods to run Git commands as set up the connection to the GIT_SSH
 * wrapper script.
 *
 * @see \Symplify\GitWrapper\Tests\GitWrapperTest
 */
final class GitWrapper
{
    /**
     * The timeout of the Git command in seconds.
     */
    private int $timeout = 60;

    /**
     * Environment variables defined in the scope of the Git command.
     *
     * @var string[]
     */
    private $env = [];

    private ?StreamOutputEventSubscriber $outputEventSubscriber = null;

    private EventDispatcherInterface | EventDispatcher $eventDispatcher;

    public function __construct(
        private string $gitBinary
    ) {
        $this->eventDispatcher = new EventDispatcher();
    }

    public function getDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function setDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function setGitBinary(string $gitBinary): void
    {
        $this->gitBinary = $gitBinary;
    }

    public function getGitBinary(): string
    {
        return $this->gitBinary;
    }

    public function setEnvVar(string $var, $value): void
    {
        $this->env[$var] = $value;
    }

    public function unsetEnvVar(string $var): void
    {
        unset($this->env[$var]);
    }

    /**
     * Returns an environment variable that is defined only in the scope of the Git command.
     *
     * @param string $var The name of the environment variable, e.g. "HOME", "GIT_SSH".
     * @param mixed $default The value returned if the environment variable is not set, defaults to null.
     * @return mixed|string
     */
    public function getEnvVar(string $var, $default = null)
    {
        return $this->env[$var] ?? $default;
    }

    /**
     * @return string[]
     */
    public function getEnvVars(): array
    {
        return $this->env;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set an alternate private key used to connect to the repository.
     *
     * This method sets the GIT_SSH environment variable to use the wrapper script included with this library. It also
     * sets the custom GIT_SSH_KEY and GIT_SSH_PORT environment variables that are used by the script.
     *
     * @param string|null $wrapper Path the the GIT_SSH wrapper script, defaults to null which uses the
     * script included with this library.
     */
    public function setPrivateKey(string $privateKey, int $port = 22, ?string $wrapper = null): void
    {
        if ($wrapper === null) {
            $wrapper = __DIR__ . '/../bin/git-ssh-wrapper.sh';
        }

        $wrapperPath = realpath($wrapper);
        if ($wrapperPath === false) {
            $message = sprintf('Path to GIT_SSH wrapper script could not be resolved "%s"', $wrapper);
            throw new GitException($message);
        }

        $privateKeyPath = realpath($privateKey);
        if ($privateKeyPath === false) {
            $message = sprintf('Path private key could not be resolved "%s"', $privateKey);
            throw new GitException($message);
        }

        $this->setEnvVar('GIT_SSH', $wrapperPath);
        $this->setEnvVar('GIT_SSH_KEY', $privateKeyPath);
        $this->setEnvVar('GIT_SSH_PORT', $port);
    }

    /**
     * Unsets the private key by removing the appropriate environment variables.
     */
    public function unsetPrivateKey(): void
    {
        $this->unsetEnvVar('GIT_SSH');
        $this->unsetEnvVar('GIT_SSH_KEY');
        $this->unsetEnvVar('GIT_SSH_PORT');
    }

    /**
     * @api
     */
    public function addOutputEventSubscriber(EventSubscriberInterface $eventSubscriber): void
    {
        $this->eventDispatcher->addSubscriber($eventSubscriber);
    }

    public function addLoggerEventSubscriber(GitLoggerEventSubscriber $gitLoggerEventSubscriber): void
    {
        $this->eventDispatcher->addSubscriber($gitLoggerEventSubscriber);
    }

    /**
     * @api
     */
    public function removeOutputEventSubscriber(EventSubscriberInterface $eventSubscriber): void
    {
        $this->eventDispatcher->removeSubscriber($eventSubscriber);
    }

    /**
     * @api
     * Set whether or not to stream real-time output to STDOUT and STDERR.
     */
    public function streamOutput(bool $streamOutput = true): void
    {
        if ($streamOutput && $this->outputEventSubscriber === null) {
            $this->outputEventSubscriber = new StreamOutputEventSubscriber();
            $this->addOutputEventSubscriber($this->outputEventSubscriber);
        }

        if (! $streamOutput && $this->outputEventSubscriber !== null) {
            $this->removeOutputEventSubscriber($this->outputEventSubscriber);
            unset($this->outputEventSubscriber);
        }
    }

    /**
     * Returns an object that interacts with a working copy.
     *
     * @param string $directory Path to the directory containing the working copy.
     */
    public function workingCopy(string $directory): GitWorkingCopy
    {
        return new GitWorkingCopy($this, $directory);
    }

    /**
     * Returns the version of the installed Git client.
     */
    public function version(): string
    {
        return $this->git('--version');
    }

    /**
     * Executes a `git init` command.
     *
     * Create an empty git repository or reinitialize an existing one.
     *
     * @param mixed[] $options An associative array of command line options.
     */
    public function init(string $directory, array $options = []): GitWorkingCopy
    {
        $git = $this->workingCopy($directory);
        $git->init($options);
        $git->setCloned(true);

        return $git;
    }

    /**
     * Executes a `git clone` command and returns a working copy object.
     *
     * Clone a repository into a new directory. Use @see GitWorkingCopy::cloneRepository() instead for more readable
     * code.
     *
     * @param string $directory The directory that the repository will be cloned into. If null is
     * passed, the directory will be generated from the URL with @see GitStrings::parseRepositoryName().
     * @param mixed[] $options
     */
    public function cloneRepository(string $repository, ?string $directory = null, array $options = []): GitWorkingCopy
    {
        if ($directory === null) {
            $directory = GitStrings::parseRepositoryName($repository);
        }

        $git = $this->workingCopy($directory);
        $git->cloneRepository($repository, $options);
        $git->setCloned(true);
        return $git;
    }

    /**
     * The command is simply a raw command line entry for everything after the Git binary. For example, a `git config
     * -l` command would be passed as `config -l` via the first argument of this method.
     *
     * @return string The STDOUT returned by the Git command.
     */
    public function git(string $commandLine, ?string $cwd = null): string
    {
        $gitCommand = new GitCommand($commandLine);
        $gitCommand->executeRaw();
        $gitCommand->setDirectory($cwd);

        return $this->run($gitCommand);
    }

    /**
     * @return string The STDOUT returned by the Git command.
     */
    public function run(GitCommand $gitCommand, ?string $cwd = null): string
    {
        $gitProcess = new GitProcess($this, $gitCommand, $cwd);

        $gitProcess->run(function ($type, $buffer) use ($gitProcess, $gitCommand): void {
            $gitOutputEvent = new GitOutputEvent($this, $gitProcess, $gitCommand, $type, $buffer);
            $this->eventDispatcher->dispatch($gitOutputEvent);
        });

        return $gitCommand->isBypassed() ? '' : $gitProcess->getOutput();
    }
}
