<?php

declare(strict_types=1);

namespace Symplify\MonorepoBuilder\Finder;

use Symfony\Component\Finder\Finder;
use Symplify\MonorepoBuilder\ValueObject\Option;
use Symplify\PackageBuilder\Parameter\ParameterProvider;
use Symplify\SmartFileSystem\Finder\FinderSanitizer;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Symplify\MonorepoBuilder\Tests\Finder\PackageComposerFinder\PackageComposerFinderTest
 */
final class PackageComposerFinder
{
    /**
     * @var string[]
     */
    private array $packageDirectories = [];

    /**
     * @var string[]
     */
    private array $packageDirectoriesExcludes = [];

    /**
     * @var SmartFileInfo[]
     */
    private array $cachedPackageComposerFiles = [];

    public function __construct(
        ParameterProvider $parameterProvider,
        private FinderSanitizer $finderSanitizer
    ) {
        $this->packageDirectories = $parameterProvider->provideArrayParameter(Option::PACKAGE_DIRECTORIES);
        $this->packageDirectoriesExcludes = $parameterProvider->provideArrayParameter(
            Option::PACKAGE_DIRECTORIES_EXCLUDES
        );
    }

    public function getRootPackageComposerFile(): SmartFileInfo
    {
        return new SmartFileInfo(getcwd() . DIRECTORY_SEPARATOR . 'composer.json');
    }

    /**
     * @return SmartFileInfo[]
     */
    public function getPackageComposerFiles(): array
    {
        if ($this->cachedPackageComposerFiles === []) {
            $finder = Finder::create()
                ->files()
                ->in($this->packageDirectories)
                // sub-directory for wrapping to phar
                ->exclude('compiler')
                // "init" command template data
                ->exclude('templates')
                ->exclude('vendor')
                // usually designed for prefixed/downgraded versions
                ->exclude('build')
                ->exclude('node_modules')
                ->name('composer.json');

            foreach ($this->packageDirectoriesExcludes as $excludeFolder) {
                $finder->exclude($excludeFolder);
            }

            if (! $this->isPHPUnit()) {
                $finder->notPath('#tests#');
            }

            $this->cachedPackageComposerFiles = $this->finderSanitizer->sanitize($finder);
        }

        return $this->cachedPackageComposerFiles;
    }

    private function isPHPUnit(): bool
    {
        // defined by PHPUnit
        return defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__');
    }
}
