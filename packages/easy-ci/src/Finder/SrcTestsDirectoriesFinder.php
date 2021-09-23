<?php

declare(strict_types=1);

namespace Symplify\EasyCI\Finder;

use Nette\Utils\Strings;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symplify\EasyCI\ValueObject\SrcAndTestsDirectories;
use Symplify\EasyTesting\PHPUnit\StaticPHPUnitEnvironment;
use Symplify\SmartFileSystem\Finder\FinderSanitizer;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Symplify\EasyCI\Tests\Finder\SrcTestsDirectoriesFinder\SrcTestsDirectoriesFinderTest
 */
final class SrcTestsDirectoriesFinder
{
    /**
     * @see https://regex101.com/r/KkSmFS/1
     * @var string
     */
    private const SRC_ONLY_REGEX = '#\bsrc\b#';

    /**
     * @see https://regex101.com/r/wzPJ72/2
     * @var string
     */
    private const TESTS_ONLY_REGEX = '#\btests\b#';

    public function __construct(
        private FinderSanitizer $finderSanitizer
    ) {
    }

    /**
     * @param string[] $directories
     */
    public function findSrcAndTestsDirectories(
        array $directories,
        bool $allowTestingDirectory = false
    ): ?SrcAndTestsDirectories {
        $fileInfos = $this->findInDirectories($directories, $allowTestingDirectory);
        if ($fileInfos === []) {
            return null;
        }

        $srcDirectories = [];
        $testsDirectories = [];

        foreach ($fileInfos as $fileInfo) {
            if ($fileInfo->endsWith('tests') && ! \str_contains($fileInfo->getRealPath(), 'src')) {
                $testsDirectories[] = $fileInfo;
            } elseif ($fileInfo->endsWith('src') && (! \str_contains(
                $fileInfo->getRealPath(),
                'tests'
            ) || StaticPHPUnitEnvironment::isPHPUnitRun())) {
                $srcDirectories[] = $fileInfo;
            }
        }

        return new SrcAndTestsDirectories($srcDirectories, $testsDirectories);
    }

    /**
     * @return SmartFileInfo[]
     */
    private function findInDirectories(array $directories, bool $allowTestingDirectory = false): array
    {
        $existingDirectories = $this->filterExistingDirectories($directories);
        if ($existingDirectories === []) {
            return [];
        }

        $finder = new Finder();
        $finder->directories()
            ->name('#(src|tests)$#')
            ->exclude('Fixture')
            ->in($existingDirectories);

        if (! $allowTestingDirectory) {
            // exclude tests/src directory nested in /tests, e.g. real project for testing
            $finder->filter(function (SplFileInfo $fileInfo): bool {
                $srcCounter = count(Strings::matchAll($fileInfo->getPathname(), self::SRC_ONLY_REGEX));

                if ($srcCounter > 1) {
                    return false;
                }

                $testsCounter = count(Strings::matchAll($fileInfo->getPathname(), self::TESTS_ONLY_REGEX));
                return $testsCounter <= 1;
            });
        }

        return $this->finderSanitizer->sanitize($finder);
    }

    /**
     * @param string[] $directories
     * @return string[]
     */
    private function filterExistingDirectories(array $directories): array
    {
        return array_filter($directories, fn (string $directory): bool => file_exists($directory));
    }
}
