<?php

namespace Symplify\EasyCodingStandard\Tests\Error\ErrorCollector\SniffRunnerSource;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This class has been created specifically to produce a warning on any print token
 * Special crafted file that trigger this sniff is located at "./warn-on-print-code.inc"
 *
 * @see \Symplify\EasyCodingStandard\Tests\Error\ErrorCollector\SniffFileProcessorReportWarningTest
 */
final class WarnOnPrintFakeSniff implements Sniff
{
    public function register()
    {
        return [T_PRINT];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $phpcsFile->addWarning('Fake warning', $stackPtr, '');
    }
}
