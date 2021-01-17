<?php

declare(strict_types=1);

namespace Symplify\ComposerJsonManipulator\Tests\ComposerJsonFactory;

use Symplify\ComposerJsonManipulator\ComposerJsonFactory;
use Symplify\ComposerJsonManipulator\FileSystem\JsonFileManager;
use Symplify\ComposerJsonManipulator\Tests\HttpKernel\ComposerJsonManipulatorKernel;
use Symplify\PackageBuilder\Testing\AbstractKernelTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

final class ComposerJsonFactoryTest extends AbstractKernelTestCase
{
    /**
     * @var ComposerJsonFactory
     */
    private $composerJsonFactory;

    /**
     * @var JsonFileManager
     */
    private $jsonFileManager;

    protected function setUp(): void
    {
        $this->bootKernel(ComposerJsonManipulatorKernel::class);
        $this->composerJsonFactory = $this->getService(ComposerJsonFactory::class);
        $this->jsonFileManager = $this->getService(JsonFileManager::class);
    }

    public function test(): void
    {
        $composerJson = $this->composerJsonFactory->createFromFilePath(__DIR__ . '/Source/some_composer.json');

        $fileInfo = $composerJson->getFileInfo();
        $this->assertInstanceOf(SmartFileInfo::class, $fileInfo);

        /** @var SmartFileInfo $fileInfo */
        $this->assertCount(2, $composerJson->getAllClassmaps());

        $this->assertSame(['directory', 'src'], $composerJson->getPsr4AndClassmapDirectories());

        $this->assertSame([
            'symplify/between' => '^8.3.45',
        ], $composerJson->getReplace());

        $this->assertSame('project', $composerJson->getType());
    }

    public function testReadAndWriteToJsonShouldBeEqual(): void
    {
        $file = __DIR__ . '/Source/full_composer.json';

        $composerJson = $this->composerJsonFactory->createFromFilePath($file);
        $actualJson = $this->jsonFileManager->encodeJsonToFileContent($composerJson->getJsonArray());

        $this->assertJsonStringEqualsJsonFile($file, $actualJson);
    }
}
