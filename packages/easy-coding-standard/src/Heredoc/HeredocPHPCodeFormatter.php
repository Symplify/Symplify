<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Heredoc;

use Nette\Utils\Strings;
use Symplify\EasyCodingStandard\Configuration\Configuration;
use Symplify\EasyCodingStandard\FixerRunner\Application\FixerFileProcessor;
use Symplify\EasyCodingStandard\SniffRunner\Application\SniffFileProcessor;
use Symplify\SmartFileSystem\SmartFileInfo;
use Symplify\SmartFileSystem\SmartFileSystem;

/**
 * @see \Symplify\EasyCodingStandard\Tests\Heredoc\HeredocPHPCodeFormatterTest
 */
final class HeredocPHPCodeFormatter
{
    /**
     * @see https://regex101.com/r/SZr0X5/1
     * @var string
     */
    private const PHP_CODE_SNIPPET_IN_HEREDOC = '#(?<opening><<<\'PHP\'\s+)(?<content>[^PHP]+\n)(?<closing>(\s+)?PHP)#ms';

    /**
     * @var SmartFileSystem
     */
    private $smartFileSystem;

    /**
     * @var FixerFileProcessor
     */
    private $fixerFileProcessor;

    /**
     * @var SniffFileProcessor
     */
    private $sniffFileProcessor;

    /**
     * @var Configuration
     */
    private $configuration;

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
            self::PHP_CODE_SNIPPET_IN_HEREDOC,
            function ($match): string {
                $fixedContent = $this->fixContent($match['content']);
                return $match['opening'] . $fixedContent . $match['closing'];
            }
        );
    }

    private function fixContent(string $content): string
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

        return ltrim($fileContent, '<?php' . PHP_EOL);
    }
}
