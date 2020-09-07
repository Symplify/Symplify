<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Formatter;

use Nette\Utils\Strings;
use Symplify\EasyCodingStandard\Configuration\Configuration;
use Symplify\EasyCodingStandard\FixerRunner\Application\FixerFileProcessor;
use Symplify\EasyCodingStandard\SniffRunner\Application\SniffFileProcessor;
use Symplify\SmartFileSystem\SmartFileInfo;
use Symplify\SmartFileSystem\SmartFileSystem;

abstract class AbstractPHPFormatter
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
        // enable fixing
        $this->configuration->resolveFromArray(['isFixer' => true]);

        return (string) Strings::replace(
            $fileInfo->getContents(),
            static::PHP_CODE_SNIPPET,
            function ($match): string {
                $fixedContent = $this->fixContent($match['content']);
                return $match['opening'] . $fixedContent . $match['closing'];
            }
        );
    }

    protected function fixContent(string $content): string
    {
        $key = md5($content);

        /** @var string $file */
        $file = sprintf('php-code-%s.php', $key);

        if (! Strings::startsWith($content, '<?php')) {
            $content = '<?php' . PHP_EOL . $content;
        }

        $fileContent = $content;

        $this->smartFileSystem->dumpFile($file, $fileContent);

        $fileInfo = new SmartFileInfo($file);
        $this->fixerFileProcessor->processFile($fileInfo);
        $this->sniffFileProcessor->processFile($fileInfo);

        $fileContent = $fileInfo->getContents();

        $this->smartFileSystem->remove($file);

        return $fileContent;
    }
}
