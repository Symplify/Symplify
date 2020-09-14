<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Formatter;

use Nette\Utils\Strings;
use Symplify\EasyCodingStandard\Configuration\Configuration;
use Symplify\EasyCodingStandard\Contract\RegexAwareFormatterInterface;
use Symplify\EasyCodingStandard\FixerRunner\Application\FixerFileProcessor;
use Symplify\EasyCodingStandard\SniffRunner\Application\SniffFileProcessor;
use Symplify\SmartFileSystem\SmartFileInfo;
use Symplify\SmartFileSystem\SmartFileSystem;
use Throwable;

abstract class AbstractPHPFormatter implements RegexAwareFormatterInterface
{
    /**
     * @var SmartFileSystem
     */
    protected $smartFileSystem;

    /**
     * @var FixerFileProcessor
     */
    protected $fixerFileProcessor;

    /**
     * @var SniffFileProcessor
     */
    protected $sniffFileProcessor;

    /**
     * @var Configuration
     */
    protected $configuration;

    public function __construct(
        SmartFileSystem $smartFileSystem,
        FixerFileProcessor $fixerFileProcessor,
        SniffFileProcessor $sniffFileProcessor,
        Configuration $configuration
    ) {
        $this->smartFileSystem = $smartFileSystem;
        $this->fixerFileProcessor = $fixerFileProcessor;
        $this->sniffFileProcessor = $sniffFileProcessor;
        $this->configuration = $configuration;
    }

    public function format(SmartFileInfo $fileInfo): string
    {
        $this->configuration->enableFixing();

        return (string) Strings::replace(
            $fileInfo->getContents(),
            $this->provideRegex(),
            function ($match): string {
                $fixedContent = $this->fixContent($match['content']);
                return rtrim($match['opening'], PHP_EOL) . PHP_EOL
                    . $fixedContent
                    . ltrim($match['closing'], PHP_EOL);
            }
        );
    }

    private function fixContent(string $content): string
    {
        $content = trim($content);
        $key = md5($content);

        /** @var string $file */
        $file = sprintf('php-code-%s.php', $key);

        $hasPreviouslyOpeningPHPTag = true;
        if (! Strings::startsWith($content, '<?php')) {
            $content = '<?php' . PHP_EOL . $content;
            $hasPreviouslyOpeningPHPTag = false;
        }

        $fileContent = $content;

        $this->smartFileSystem->dumpFile($file, $fileContent);

        $fileInfo = new SmartFileInfo($file);
        try {
//            $this->skipStrictTypesDeclaration();
            $this->fixerFileProcessor->processFile($fileInfo);
            $this->sniffFileProcessor->processFile($fileInfo);

            $fileContent = $fileInfo->getContents();
        } catch (Throwable $throwable) {
            // Skipped parsed error when processing php file
        } finally {
            $this->smartFileSystem->remove($file);
        }

        if (! $hasPreviouslyOpeningPHPTag) {
            $fileContent = substr($fileContent, 6);
        }

        return rtrim($fileContent, PHP_EOL) . PHP_EOL;
    }
}
