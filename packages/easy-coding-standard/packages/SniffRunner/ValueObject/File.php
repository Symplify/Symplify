<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\SniffRunner\ValueObject;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File as BaseFile;
use PHP_CodeSniffer\Fixer;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\ForLoopShouldBeWhileLoopSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Classes\PropertyDeclarationSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Methods\MethodDeclarationSniff;
use PHP_CodeSniffer\Util\Common;
use Symplify\EasyCodingStandard\Console\Style\EasyCodingStandardStyle;
use Symplify\EasyCodingStandard\SniffRunner\DataCollector\SniffMetadataCollector;
use Symplify\EasyCodingStandard\SniffRunner\Exception\File\NotImplementedException;
use Symplify\EasyCodingStandard\SniffRunner\ValueObject\Error\CodingStandardError;
use Symplify\Skipper\Skipper\Skipper;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Symplify\EasyCodingStandard\Tests\SniffRunner\ValueObject\FileTest
 */
final class File extends BaseFile
{
    /**
     * Explicit list for classes that use only warnings. ECS only reports errors, so this one promotes them to error.
     *
     * @var array<class-string<Sniff>>
     */
    private const REPORT_WARNINGS_SNIFFS = [
        AssignmentInConditionSniff::class,
        PropertyDeclarationSniff::class,
        MethodDeclarationSniff::class,
        ForLoopShouldBeWhileLoopSniff::class,
    ];

    /**
     * Additional list for sniff classes to report warnings as errors.
     *
     * @var array<class-string<Sniff>>
     */
    private array $reportWarnings = [];

    /**
     * @var string
     */
    public $tokenizerType = 'PHP';

    /**
     * @var string|null
     */
    private $activeSniffClass;

    /**
     * @var string|null
     */
    private $previousActiveSniffClass;

    /**
     * @var Sniff[][]
     */
    private array $tokenListeners = [];

    private ?SmartFileInfo $fileInfo = null;

    public function __construct(
        string $path,
        string $content,
        Fixer $fixer,
        private Skipper $skipper,
        private SniffMetadataCollector $sniffMetadataCollector,
        private EasyCodingStandardStyle $easyCodingStandardStyle
    ) {
        $this->path = $path;
        $this->content = $content;

        // this property cannot be promoted as defined in constructor
        $this->fixer = $fixer;

        $this->eolChar = Common::detectLineEndings($content);

        // compat
        if (! defined('PHP_CODESNIFFER_CBF')) {
            define('PHP_CODESNIFFER_CBF', false);
        }

        // parent required
        $this->config = new Config([], false);
        $this->config->tabWidth = 4;
        $this->config->annotations = false;
        $this->config->encoding = 'UTF-8';
    }

    /**
     * Mimics @see
     * https://github.com/squizlabs/PHP_CodeSniffer/blob/e4da24f399d71d1077f93114a72e305286020415/src/Files/File.php#L310
     */
    public function process(): void
    {
        $this->parse();
        $this->fixer->startFile($this);

        foreach ($this->tokens as $stackPtr => $token) {
            if (! isset($this->tokenListeners[$token['code']])) {
                continue;
            }

            foreach ($this->tokenListeners[$token['code']] as $sniff) {
                if ($this->skipper->shouldSkipElementAndFileInfo($sniff, $this->fileInfo)) {
                    continue;
                }

                $this->reportActiveSniffClass($sniff);

                $sniff->process($this, $stackPtr);
            }
        }

        $this->fixedCount += $this->fixer->getFixCount();
    }

    public function getErrorCount(): void
    {
        throw new NotImplementedException(sprintf('Method "%s" is not needed to be public.', __METHOD__,));
    }

    /**
     * @return mixed[]
     */
    public function getErrors(): void
    {
        throw new NotImplementedException(sprintf('Method "%s" is not needed to be public.', __METHOD__,));
    }

    /**
     * Delegate to addError().
     *
     * {@inheritdoc}
     */
    public function addFixableError($error, $stackPtr, $code, $data = [], $severity = 0): bool
    {
        $fullyQualifiedCode = $this->resolveFullyQualifiedCode($code);
        $this->sniffMetadataCollector->addAppliedSniff($fullyQualifiedCode);

        return ! $this->shouldSkipError($error, $code, $data);
    }

    public function addError($error, $stackPtr, $code, $data = [], $severity = 0, $fixable = false): bool
    {
        if ($this->shouldSkipError($error, $code, $data)) {
            return false;
        }

        return parent::addError($error, $stackPtr, $code, $data, $severity, $fixable);
    }

    /**
     * Allow only specific classes
     *
     * {@inheritdoc}
     */
    public function addWarning($warning, $stackPtr, $code, $data = [], $severity = 0, $fixable = false): bool
    {
        if (! $this->isSniffClassWarningAllowed($this->activeSniffClass)) {
            return false;
        }

        return $this->addError($warning, $stackPtr, $code, $data, $severity, $fixable);
    }

    /**
     * @param array<int|string, Sniff[]> $tokenListeners
     */
    public function processWithTokenListenersAndFileInfo(array $tokenListeners, SmartFileInfo $fileInfo): void
    {
        $this->tokenListeners = $tokenListeners;
        $this->fileInfo = $fileInfo;
        $this->process();
    }

    /**
     * Delegated from addError().
     *
     * {@inheritdoc}
     */
    protected function addMessage(
        $isError,
        $message,
        $line,
        $column,
        $sniffClassOrCode,
        $data,
        $severity,
        $isFixable = false
    ): bool {
        // skip warnings
        if (! $isError) {
            return false;
        }

        $message = $data !== [] ? vsprintf($message, $data) : $message;
        $codingStandardError = new CodingStandardError($line, $message, $sniffClassOrCode, $this->getFilename());
        $this->sniffMetadataCollector->addCodingStandardError($codingStandardError);

        if ($isFixable) {
            return $isFixable;
        }

        // do not add non-fixable errors twice
        return $this->fixer->loops === 0;
    }

    private function reportActiveSniffClass(Sniff $sniff): void
    {
        // used in other places later
        $this->activeSniffClass = $sniff::class;

        if (! $this->easyCodingStandardStyle->isDebug()) {
            return;
        }

        if ($this->previousActiveSniffClass === $this->activeSniffClass) {
            return;
        }

        $this->easyCodingStandardStyle->writeln('     [sniff] ' . $this->activeSniffClass);
        $this->previousActiveSniffClass = $this->activeSniffClass;
    }

    private function resolveFullyQualifiedCode(string $sniffClassOrCode): string
    {
        if (class_exists($sniffClassOrCode)) {
            return $sniffClassOrCode;
        }

        return $this->activeSniffClass . '.' . $sniffClassOrCode;
    }

    /**
     * @param string[] $data
     */
    private function shouldSkipError(string $error, string $code, array $data): bool
    {
        $fullyQualifiedCode = $this->resolveFullyQualifiedCode($code);

        if ($this->skipper->shouldSkipElementAndFileInfo($fullyQualifiedCode, $this->fileInfo)) {
            return true;
        }

        $message = $data !== [] ? vsprintf($error, $data) : $error;

        return $this->skipper->shouldSkipElementAndFileInfo($message, $this->fileInfo);
    }

    private function isSniffClassWarningAllowed(string $sniffClass): bool
    {
        $reportWarningsSniffClasses = array_merge(self::REPORT_WARNINGS_SNIFFS, $this->reportWarnings);
        foreach ($reportWarningsSniffClasses as $reportWarningsSniff) {
            if (is_a($sniffClass, $reportWarningsSniff, true)) {
                return true;
            }
        }

        return false;
    }

    public function setReportWarnings(array $reportWarnings): void
    {
        $this->reportWarnings = $reportWarnings;
    }
}
