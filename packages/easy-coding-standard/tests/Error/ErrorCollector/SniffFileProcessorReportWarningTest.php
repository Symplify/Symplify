<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Tests\Error\ErrorCollector;

use Symplify\EasyCodingStandard\Caching\ChangedFilesDetector;
use Symplify\EasyCodingStandard\HttpKernel\EasyCodingStandardKernel;
use Symplify\EasyCodingStandard\SniffRunner\Application\SniffFileProcessor;
use Symplify\EasyCodingStandard\SniffRunner\ValueObject\Error\CodingStandardError;
use Symplify\EasyCodingStandard\Tests\Error\ErrorCollector\SniffRunnerSource\WarnOnPrintFakeSniff;
use Symplify\EasyCodingStandard\ValueObject\Configuration;
use Symplify\EasyCodingStandard\ValueObject\Error\FileDiff;
use Symplify\PackageBuilder\Testing\AbstractKernelTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

final class SniffFileProcessorReportWarningTest extends AbstractKernelTestCase
{
    private SniffFileProcessor $sniffFileProcessor;

    protected function setUp(): void
    {
        $this->bootKernelWithConfigs(
            EasyCodingStandardKernel::class,
            [__DIR__ . '/SniffRunnerSource/config-report-warnings.php']
        );

        $this->sniffFileProcessor = $this->getService(SniffFileProcessor::class);

        $changedFilesDetector = $this->getService(ChangedFilesDetector::class);
        $changedFilesDetector->clearCache();
    }

    /** @return mixed[][] */
    public function provider(): array
    {
        return [
            'no report' => [0, new Configuration()],
            'report is set up' => [1, new Configuration(reportWarnings: [WarnOnPrintFakeSniff::class])],
        ];
    }

    /** @dataProvider provider */
    public function test(int $expectedErrorCount, Configuration $configuration): void
    {
        $smartFileInfo = new SmartFileInfo(__DIR__ . '/SniffRunnerSource/warn-on-print-code.inc');
        $errorsAndFileDiffs = $this->sniffFileProcessor->processFile($smartFileInfo, $configuration);

        /** @var FileDiff[] $fileDiffs */
        $fileDiffs = $errorsAndFileDiffs['file_diffs'] ?? [];
        $this->assertCount(0, $fileDiffs);

        /** @var CodingStandardError[] $codingStandardErrors */
        $codingStandardErrors = $errorsAndFileDiffs['coding_standard_errors'] ?? [];
        $this->assertCount($expectedErrorCount, $codingStandardErrors);
    }
}
