<?php

declare(strict_types=1);

namespace Symplify\SmartFileSystem\Finder;

use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Symplify\SmartFileSystem\Tests\Finder\FinderSanitizer\FinderSanitizerTest
 */
final class FinderSanitizer
{
    /**
     * @return SmartFileInfo[]
     */
    public function sanitize(Finder $finder): array
    {
        $smartFileInfos = [];
        foreach ($finder as $fileInfo) {
            if (! $this->isFileInfoValid($fileInfo)) {
                continue;
            }

            /** @var string $realPath */
            $realPath = $fileInfo->getRealPath();

            $smartFileInfos[] = new SmartFileInfo($realPath);
        }

        return $smartFileInfos;
    }

    private function isFileInfoValid(SplFileInfo $fileInfo): bool
    {
        return (bool) $fileInfo->getRealPath();
    }
}
