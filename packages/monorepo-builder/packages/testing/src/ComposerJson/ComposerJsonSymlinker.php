<?php

declare(strict_types=1);

namespace Symplify\MonorepoBuilder\Testing\ComposerJson;

use Symplify\ComposerJsonManipulator\ValueObject\ComposerJsonSection;
use Symplify\MonorepoBuilder\FileSystem\ComposerJsonProvider;
use Symplify\MonorepoBuilder\Finder\PackageComposerFinder;
use Symplify\MonorepoBuilder\Testing\PathResolver\PackagePathResolver;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Symplify\MonorepoBuilder\Testing\Tests\ComposerJson\ComposerJsonSymlinkerTest
 */
final class ComposerJsonSymlinker
{
    /**
     * @var ComposerJsonProvider
     */
    private $composerJsonProvider;

    /**
     * @var PackagePathResolver
     */
    private $packagePathResolver;

    /**
     * @var PackageComposerFinder
     */
    private $packageComposerFinder;

    public function __construct(
        ComposerJsonProvider $composerJsonProvider,
        PackagePathResolver $packagePathResolver,
        PackageComposerFinder $packageComposerFinder
    ) {
        $this->composerJsonProvider = $composerJsonProvider;
        $this->packagePathResolver = $packagePathResolver;
        $this->packageComposerFinder = $packageComposerFinder;
    }

    /**
     * @param mixed[] $packageComposerJson
     * @param string[] $packageNames
     * @return mixed[]
     */
    public function decoratePackageComposerJsonWithPackageSymlinks(
        array $packageComposerJson,
        array $packageNames,
        SmartFileInfo $mainComposerJsonFileInfo
    ): array {
        // @see https://getcomposer.org/doc/05-repositories.md#path
        $packageComposerFiles = $this->packageComposerFinder->getPackageComposerFiles();
        foreach ($packageNames as $packageName) {
            $usedPackageFileInfo = $this->composerJsonProvider->getPackageFileInfoByName($packageName, $packageComposerFiles);

            $relativePathToLocalPackage = $this->packagePathResolver->resolveRelativePathToLocalPackage(
                $mainComposerJsonFileInfo,
                $usedPackageFileInfo
            );

            $repositoriesContent = [
                'type' => 'path',
                'url' => $relativePathToLocalPackage,
                // we need hard copy of files, as in normal composer install of standalone package
                'options' => [
                    'symlink' => false,
                ],
            ];

            if (array_key_exists(ComposerJsonSection::REPOSITORIES, $packageComposerJson)) {
                array_unshift($packageComposerJson[ComposerJsonSection::REPOSITORIES], $repositoriesContent);
            } else {
                $packageComposerJson[ComposerJsonSection::REPOSITORIES][] = $repositoriesContent;
            }
        }

        return $packageComposerJson;
    }
}
