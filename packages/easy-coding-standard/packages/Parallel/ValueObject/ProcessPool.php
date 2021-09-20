<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Parallel\ValueObject;

use React\Socket\TcpServer;
use Symplify\EasyCodingStandard\Parallel\Exception\ParallelShouldNotHappenException;

/**
 * Used from https://github.com/phpstan/phpstan-src/blob/master/src/Parallel/ProcessPool.php
 */
final class ProcessPool
{
    /**
     * @var TcpServer
     */
    private $tcpServer;

    /**
     * @var array<string, ParallelProcess>
     */
    private $processes = [];

    public function __construct(TcpServer $tcpServer)
    {
        $this->tcpServer = $tcpServer;
    }

    public function getProcess(string $identifier): ParallelProcess
    {
        if (! \array_key_exists($identifier, $this->processes)) {
            throw new ParallelShouldNotHappenException(\sprintf('Process %s not found.', $identifier));
        }
        return $this->processes[$identifier];
    }

    public function attachProcess(string $identifier, ParallelProcess $process): void
    {
        $this->processes[$identifier] = $process;
    }

    public function tryQuitProcess(string $identifier): void
    {
        if (! \array_key_exists($identifier, $this->processes)) {
            return;
        }
        $this->quitProcess($identifier);
    }

    public function quitProcess(string $identifier): void
    {
        $parallelProcess = $this->getProcess($identifier);
        $parallelProcess->quit();

        unset($this->processes[$identifier]);
        if (\count($this->processes) !== 0) {
            return;
        }
        $this->tcpServer->close();
    }

    public function quitAll(): void
    {
        foreach (\array_keys($this->processes) as $identifier) {
            $this->quitProcess($identifier);
        }
    }
}
