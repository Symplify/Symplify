<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\SniffRunner\Application;

use PHP_CodeSniffer\Fixer;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use PhpCsFixer\Differ\DifferInterface;
use Symplify\EasyCodingStandard\Contract\Application\FileProcessorInterface;
use Symplify\EasyCodingStandard\Error\FileDiffFactory;
use Symplify\EasyCodingStandard\Parallel\ValueObject\Bridge;
use Symplify\EasyCodingStandard\SniffRunner\DataCollector\SniffMetadataCollector;
use Symplify\EasyCodingStandard\SniffRunner\File\FileFactory;
use Symplify\EasyCodingStandard\SniffRunner\ValueObject\Error\CodingStandardError;
use Symplify\EasyCodingStandard\SniffRunner\ValueObject\File;
use Symplify\EasyCodingStandard\ValueObject\Configuration;
use Symplify\EasyCodingStandard\ValueObject\Error\FileDiff;
use Symplify\SmartFileSystem\SmartFileInfo;
use Symplify\SmartFileSystem\SmartFileSystem;

/**
 * @see \Symplify\EasyCodingStandard\Tests\Error\ErrorCollector\SniffFileProcessorTest
 */
final class SniffFileProcessor implements FileProcessorInterface
{
    /**
     * @var Sniff[]
     */
    private $sniffs = [];

    /**
     * @var array<int|string, Sniff[]>
     */
    private array $tokenListeners = [];

    /**
     * @param Sniff[] $sniffs
     */
    public function __construct(
        private Fixer $fixer,
        private FileFactory $fileFactory,
        private DifferInterface $differ,
        private SniffMetadataCollector $sniffMetadataCollector,
        private SmartFileSystem $smartFileSystem,
        private FileDiffFactory $fileDiffFactory,
        array $sniffs = []
    ) {
        $this->addCompatibilityLayer();

        foreach ($sniffs as $sniff) {
            $this->addSniff($sniff);
        }
    }

    public function addSniff(Sniff $sniff): void
    {
        $this->sniffs[] = $sniff;
        $tokens = $sniff->register();
        foreach ($tokens as $token) {
            $this->tokenListeners[$token][] = $sniff;
        }
    }

    /**
     * @return Sniff[]
     */
    public function getCheckers(): array
    {
        return $this->sniffs;
    }

    /**
     * @return array<string, array<FileDiff|CodingStandardError>>
     */
    public function processFile(SmartFileInfo $smartFileInfo, Configuration $configuration): array
    {
        $this->sniffMetadataCollector->reset();

        $errorsAndDiffs = [];

        $file = $this->fileFactory->createFromFileInfo($smartFileInfo);

        $file->setReportWarnings($configuration->getReportWarnings());

        $this->fixFile($file, $this->fixer, $smartFileInfo, $this->tokenListeners);

        // add coding standard errors
        $codingStandardErrors = $this->sniffMetadataCollector->getCodingStandardErrors();
        if ($codingStandardErrors !== []) {
            $errorsAndDiffs[Bridge::CODING_STANDARD_ERRORS] = $codingStandardErrors;
        }

        // add diff
        if ($smartFileInfo->getContents() !== $this->fixer->getContents()) {
            $diff = $this->differ->diff($smartFileInfo->getContents(), $this->fixer->getContents());

            $appliedCheckers = $this->sniffMetadataCollector->getAppliedSniffs();

            $fileDiff = $this->fileDiffFactory->createFromDiffAndAppliedCheckers(
                $smartFileInfo,
                $diff,
                $appliedCheckers
            );

            $errorsAndDiffs[Bridge::FILE_DIFFS][] = $fileDiff;
        }

        if ($configuration->isFixer()) {
            $this->smartFileSystem->dumpFile($file->getFilename(), $this->fixer->getContents());
        }

        return $errorsAndDiffs;
    }

    /**
     * For tests or printing contenet
     */
    public function processFileToString(SmartFileInfo $smartFileInfo): string
    {
        $file = $this->fileFactory->createFromFileInfo($smartFileInfo);
        $this->fixFile($file, $this->fixer, $smartFileInfo, $this->tokenListeners);

        return $this->fixer->getContents();
    }

    private function addCompatibilityLayer(): void
    {
        if (! defined('PHP_CODESNIFFER_VERBOSITY')) {
            // initalize token with INT type, otherwise php-cs-fixer and php-parser breaks
            if (! defined('T_MATCH')) {
                define('T_MATCH', 5000);
            }

            define('PHP_CODESNIFFER_VERBOSITY', 0);
            new Tokens();
        }
    }

    /**
     * Mimics @see \PHP_CodeSniffer\Files\File::process()
     *
     * @see \PHP_CodeSniffer\Fixer::fixFile()
     *
     * @param array<int|string, Sniff[]> $tokenListeners
     */
    private function fixFile(File $file, Fixer $fixer, SmartFileInfo $smartFileInfo, array $tokenListeners): void
    {
        $previousContent = $smartFileInfo->getContents();
        $this->fixer->loops = 0;

        do {
            // Only needed once file content has changed.
            $content = $previousContent;

            $file->setContent($content);
            $file->processWithTokenListenersAndFileInfo($tokenListeners, $smartFileInfo);

            // fixed content
            $previousContent = $fixer->getContents();
            ++$this->fixer->loops;
        } while ($previousContent !== $content);
    }
}
