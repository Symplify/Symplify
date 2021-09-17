<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\ValueObject;

use Symplify\EasyCodingStandard\Console\Output\ConsoleOutputFormatter;

final class Configuration
{
    /**
     * @param string[] $sources
     * @param string[] $reportWarnings
     */
    public function __construct(
        private bool $isFixer = false,
        private bool $shouldClearCache = false,
        private bool $showProgressBar = true,
        private bool $showErrorTable = true,
        private array $sources = [],
        private string $outputFormat = ConsoleOutputFormatter::NAME,
        private bool $doesMatchGitDiff = false,
        private bool $isParallel = false,
        private ?string $config = null,
        private array $reportWarnings = [],
    ) {
    }

    public function isFixer(): bool
    {
        return $this->isFixer;
    }

    public function shouldClearCache(): bool
    {
        return $this->shouldClearCache;
    }

    public function shouldShowProgressBar(): bool
    {
        return $this->showProgressBar;
    }

    public function shouldShowErrorTable(): bool
    {
        return $this->showErrorTable;
    }

    /**
     * @return string[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function getOutputFormat(): string
    {
        return $this->outputFormat;
    }

    public function doesMatchGitDiff(): bool
    {
        return $this->doesMatchGitDiff;
    }

    public function isParallel(): bool
    {
        return $this->isParallel;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    /**
     * @return string[]
     */
    public function getReportWarnings(): array
    {
        return $this->reportWarnings;
    }
}
