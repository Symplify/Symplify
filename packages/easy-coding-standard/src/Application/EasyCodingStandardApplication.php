<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Application;

use Symplify\EasyCodingStandard\Caching\ChangedFilesDetector;
use Symplify\EasyCodingStandard\Configuration\Configuration;
use Symplify\EasyCodingStandard\Console\Style\EasyCodingStandardStyle;
use Symplify\EasyCodingStandard\FileSystem\FileFilter;
use Symplify\EasyCodingStandard\Finder\SourceFinder;
use Symplify\EasyCodingStandard\Parallel\Application\ParallelFileProcessor;
use Symplify\SmartFileSystem\SmartFileInfo;

final class EasyCodingStandardApplication
{
    public function __construct(
        private EasyCodingStandardStyle $easyCodingStandardStyle,
        private SourceFinder $sourceFinder,
        private ChangedFilesDetector $changedFilesDetector,
        private Configuration $configuration,
        private FileFilter $fileFilter,
        private SingleFileProcessor $singleFileProcessor,
        private ParallelFileProcessor $parallelFileProcessor,
    ) {
    }

    /**
     * @return int Processed file count
     */
    public function run(): int
    {
        // 1. find files in sources
        $files = $this->sourceFinder->find(
            $this->configuration->getSources(),
            $this->configuration->doesMatchGitDiff()
        );

        // 2. clear cache
        if ($this->configuration->shouldClearCache()) {
            $this->changedFilesDetector->clearCache();
        } else {
            $files = $this->fileFilter->filterOnlyChangedFiles($files);
        }

        // no files found
        $filesCount = count($files);
        if ($filesCount === 0) {
            return $filesCount;
        }

        // process found files by each processors
        $this->processFoundFiles($files);

        return $filesCount;
    }

    /**
     * @param SmartFileInfo[] $fileInfos
     */
    private function processFoundFiles(array $fileInfos): void
    {
        // 3. start progress bar
        if ($this->configuration->shouldShowProgressBar() && ! $this->easyCodingStandardStyle->isDebug()) {
            $this->easyCodingStandardStyle->progressStart(count($fileInfos));

            // show more data on progres bar
            if ($this->easyCodingStandardStyle->isVerbose()) {
                $this->easyCodingStandardStyle->enableDebugProgressBar();
            }
        }

        foreach ($fileInfos as $fileInfo) {
            if ($this->easyCodingStandardStyle->isDebug()) {
                $this->easyCodingStandardStyle->writeln(' [file] ' . $fileInfo->getRelativeFilePathFromCwd());
            }

            $this->singleFileProcessor->processFileInfo($fileInfo);
            if ($this->easyCodingStandardStyle->isDebug()) {
                continue;
            }

            if (! $this->configuration->shouldShowProgressBar()) {
                continue;
            }

            $this->easyCodingStandardStyle->progressAdvance();
        }
    }
}
