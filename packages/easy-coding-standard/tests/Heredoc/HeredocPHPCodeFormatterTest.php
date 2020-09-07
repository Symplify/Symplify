<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Tests\Heredoc;

use Iterator;
use Symfony\Component\Console\Output\OutputInterface;
use Symplify\EasyCodingStandard\Console\Style\EasyCodingStandardStyle;
use Symplify\EasyCodingStandard\Heredoc\HeredocPHPCodeFormatter;
use Symplify\EasyCodingStandard\HttpKernel\EasyCodingStandardKernel;
use Symplify\EasyTesting\DataProvider\StaticFixtureFinder;
use Symplify\EasyTesting\StaticFixtureSplitter;
use Symplify\PackageBuilder\Tests\AbstractKernelTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * For testing approach @see https://github.com/symplify/easy-testing
 */
final class HeredocPHPCodeFormatterTest extends AbstractKernelTestCase
{
    /**
     * @var HeredocPHPCodeFormatter
     */
    private $HeredocPHPFormatter;

    protected function setUp(): void
    {
        self::bootKernelWithConfigs(EasyCodingStandardKernel::class, [__DIR__ . '/config/array_fixer.php']);
        $this->HeredocPHPFormatter = self::$container->get(HeredocPHPCodeFormatter::class);

        /** @var EasyCodingStandardStyle $easyCodingStandardSymfonyStyle */
        $easyCodingStandardSymfonyStyle = self::$container->get(EasyCodingStandardStyle::class);
        $easyCodingStandardSymfonyStyle->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    }

    /**
     * @dataProvider provideData()
     */
    public function test(SmartFileInfo $fixtureFileInfo): void
    {
        $inputAndExpectedFileInfos = StaticFixtureSplitter::splitFileInfoToLocalInputAndExpectedFileInfos(
            $fixtureFileInfo
        );

        $changedContent = $this->HeredocPHPFormatter->format($inputAndExpectedFileInfos->getInputFileInfo());
        $expectedContent = $inputAndExpectedFileInfos->getExpectedFileInfo()->getContents();
        $this->assertSame($expectedContent, $changedContent, $fixtureFileInfo->getRelativeFilePathFromCwd());
    }

    public function provideData(): Iterator
    {
        return StaticFixtureFinder::yieldDirectory(__DIR__ . '/Fixture', '*.php.inc');
    }
}
