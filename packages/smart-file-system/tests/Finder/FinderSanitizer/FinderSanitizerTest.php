<?php

declare(strict_types=1);

namespace Symplify\SmartFileSystem\Tests\Finder\FinderSanitizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symplify\SmartFileSystem\Finder\FinderSanitizer;
use Symplify\SmartFileSystem\SmartFileInfo;

final class FinderSanitizerTest extends TestCase
{
    private FinderSanitizer $finderSanitizer;

    protected function setUp(): void
    {
        $this->markTestSkipped('This test is skipped because the service is not really useful');

        $this->finderSanitizer = new FinderSanitizer();
    }

    public function testSymfonyFinder(): void
    {
        $symfonyFinder = Finder::create()
            ->files()
            ->in(__DIR__ . '/Source');

        $fileInfos = iterator_to_array($symfonyFinder->getIterator());

        $this->assertCount(2, $fileInfos);
        $files = $this->finderSanitizer->sanitize($symfonyFinder);
        $this->assertCount(2, $files);

        $this->assertFilesEqualFixtureFiles($files[0], $files[1]);
    }

    /**
     * On different OS the order of the two files can differ, only symfony finder would have a sort function, nette
     * finder does not. so we test if the correct files are there but ignore the order.
     */
    private function assertFilesEqualFixtureFiles(
        SmartFileInfo $firstSmartFileInfo,
        SmartFileInfo $secondSmartFileInfo
    ): void {
        $this->assertFileIsFromFixtureDirAndHasCorrectClass($firstSmartFileInfo);
        $this->assertFileIsFromFixtureDirAndHasCorrectClass($secondSmartFileInfo);

        // order agnostic file check
        $this->assertTrue(
            (\str_ends_with($firstSmartFileInfo->getRelativeFilePath(), 'NestedDirectory/FileWithClass.php')
            &&
            \str_ends_with($secondSmartFileInfo->getRelativeFilePath(), 'NestedDirectory/EmptyFile.php'))
            ||
            (\str_ends_with($firstSmartFileInfo->getRelativeFilePath(), 'NestedDirectory/EmptyFile.php')
            &&
            \str_ends_with($secondSmartFileInfo->getRelativeFilePath(), 'NestedDirectory/FileWithClass.php'))
        );
    }

    private function assertFileIsFromFixtureDirAndHasCorrectClass(SmartFileInfo $smartFileInfo): void
    {
        $this->assertInstanceOf(SplFileInfo::class, $smartFileInfo);

        $this->assertStringEndsWith('NestedDirectory', $smartFileInfo->getRelativeDirectoryPath());
    }
}
