<?php

declare(strict_types=1);

namespace Symplify\EasyCI\Tests\Twig\TwigTemplateAnalyzer\ConstantPathTwigTemplateAnalyzer;

use Iterator;
use Symplify\EasyCI\HttpKernel\EasyCIKernel;
use Symplify\EasyCI\Twig\TwigTemplateAnalyzer\ConstantPathTwigTemplateAnalyzer;
use Symplify\PackageBuilder\Testing\AbstractKernelTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

final class ConstantPathTwigTemplateAnalyzerTest extends AbstractKernelTestCase
{
    private ConstantPathTwigTemplateAnalyzer $missingClassConstantTwigAnalyzer;

    protected function setUp(): void
    {
        $this->bootKernel(EasyCIKernel::class);
        $this->missingClassConstantTwigAnalyzer = $this->getService(ConstantPathTwigTemplateAnalyzer::class);
    }

    /**
     * @dataProvider provideData()
     */
    public function test(SmartFileInfo $inputFileInfo, int $expectedErrorCount): void
    {
        $templateErrors = $this->missingClassConstantTwigAnalyzer->analyze([$inputFileInfo]);
        $this->assertCount($expectedErrorCount, $templateErrors);
    }

    /**
     * @return Iterator<int[]|SmartFileInfo[]>
     */
    public function provideData(): Iterator
    {
        yield [new SmartFileInfo(__DIR__ . '/Fixture/path_with_constant.twig'), 0];
        yield [new SmartFileInfo(__DIR__ . '/Fixture/path_with_string.twig'), 1];
    }
}
