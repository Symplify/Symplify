<?php

declare(strict_types=1);

namespace Symplify\VendorPatches\Composer;

use Symplify\ComposerJsonManipulator\ComposerJsonFactory;
use Symplify\ComposerJsonManipulator\FileSystem\JsonFileManager;

final class ComposerPatchesConfigurationUpdater
{
    /**
     * @var ComposerJsonFactory
     */
    private $composerJsonFactory;

    /**
     * @var JsonFileManager
     */
    private $jsonFileManager;

    public function __construct(ComposerJsonFactory $composerJsonFactory, JsonFileManager $jsonFileManager)
    {
        $this->composerJsonFactory = $composerJsonFactory;
        $this->jsonFileManager = $jsonFileManager;
    }

    /**
     * Adds the patches to the composer.json file. Any patches that are already listed
     * in the composer.json file are kept, unless the referenced patch has been deleted
     * from disk.
     *
     * @param mixed[] $newPatches An array in the form [ $packageroot => [ $patches ] ]
     */
    public function updateComposerJson(array $newPatches): void
    {
        $composerJsonFilePath = getcwd() . '/composer.json';
        $composerJson = $this->composerJsonFactory->createFromFilePath($composerJsonFilePath);

        // create new 'patches' section
        $extraSection = $composerJson->getExtra();
        $patches = array_merge_recursive($extraSection['patches'] ?? [], $newPatches);

        // remove any patches that have been deleted from disk
        foreach ($patches as $package=>$paths) {
            $patches[$package] = array_filter(array_unique($paths), function($path) {
                return file_exists(getcwd() . '/' . $path);
            });
        }

        // update the composer.json file
        $composerJson->setExtra(array_merge($extraSection, ['patches'=>$patches]));
        $this->jsonFileManager->printComposerJsonToFilePath($composerJson, $composerJsonFilePath);
    }
}
