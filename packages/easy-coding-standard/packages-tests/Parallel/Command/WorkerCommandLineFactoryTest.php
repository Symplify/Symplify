<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Tests\Parallel\Command;

use Iterator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symplify\EasyCodingStandard\Console\Command\CheckCommand;
use Symplify\EasyCodingStandard\HttpKernel\EasyCodingStandardKernel;
use Symplify\EasyCodingStandard\Parallel\Command\WorkerCommandLineFactory;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\PackageBuilder\Testing\AbstractKernelTestCase;

final class WorkerCommandLineFactoryTest extends AbstractKernelTestCase
{
    private WorkerCommandLineFactory $workerCommandLineFactory;

    private CheckCommand $checkCommand;

    protected function setUp(): void
    {
        $this->bootKernel(EasyCodingStandardKernel::class);
        $this->workerCommandLineFactory = $this->getService(WorkerCommandLineFactory::class);
        $this->checkCommand = $this->getService(CheckCommand::class);
    }

    /**
     * @dataProvider provideData()
     */
    public function test(array $inputParameters, string $expectedCommand): void
    {
        $commandDefinition = $this->prepareCheckCommandDefinition();
        $arrayInput = new ArrayInput($inputParameters, $commandDefinition);

        $workerCommandLine = $this->workerCommandLineFactory->create('main_script', null, $arrayInput);

        $this->assertSame($expectedCommand, $workerCommandLine);
    }

    public function provideData(): Iterator
    {
        $relativeFilePath = substr(__FILE__, strlen(getcwd()) + 1);

        yield [
            [
                'command' => 'check',
                Option::PATHS => ['src'],
                '--' . Option::FIX => true,
            ],
            "'" . PHP_BINARY . "' 'main_script' '--colors' '" . $relativeFilePath . "' worker --fix --output-format 'console' 'src'",
        ];
    }

    private function prepareCheckCommandDefinition(): InputDefinition
    {
        $commandDefinition = $this->checkCommand->getDefinition();

        // not sure why, but the 1st argument "command" is missing; this is needed for a command name
        $arguments = $commandDefinition->getArguments();
        $commandInputArgument = new InputArgument('command', InputArgument::REQUIRED);
        $arguments = array_merge([$commandInputArgument], $arguments);

        $commandDefinition->setArguments($arguments);

        return $commandDefinition;
    }
}
