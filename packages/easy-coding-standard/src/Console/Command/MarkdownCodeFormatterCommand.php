<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symplify\EasyCodingStandard\Application\SingleFileProcessor;
use Symplify\EasyCodingStandard\Configuration\Exception\NoMarkdownFileException;
use Symplify\SmartFileSystem\SmartFileInfo;
use Symplify\SmartFileSystem\SmartFileSystem;

final class MarkdownCodeFormatterCommand extends Command
{
    /**
     * @var SmartFileSystem
     */
    private $smartFileSystem;

    /**
     * @var SingleFileProcessor
     */
    private $singleFileProcessor;

    public function __construct(SmartFileSystem $smartFileSystem, SingleFileProcessor $singleFileProcessor)
    {
        $this->smartFileSystem = $smartFileSystem;
        $this->singleFileProcessor = $singleFileProcessor;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('markdown-code-format');
        $this->setDescription('Format markdown code');
        $this->addArgument('markdown-file', InputArgument::REQUIRED, 'The markdown file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $markdownFile */
        $markdownFile = $input->getArgument('markdown-file');
        if (! file_exists($markdownFile)) {
            throw new NoMarkdownFileException(sprintf('Markdown file %s not found', $markdownFile));
        }

        /** @var string $content */
        $content = file_get_contents($markdownFile);
        preg_match_all('#\`\`\`php\s+([^\`\`\`]+)\s+\`\`\`#', $content, $matches);

        if ($matches[1] === []) {
            return 0;
        }

        foreach ($matches[1] as $key => $match) {
            $file = sprintf('php-code-%s.php', $key);
            $match = ltrim($match, '<?php');
            $match = '<?php' . PHP_EOL . trim($match);
            $this->smartFileSystem->dumpFile($file, $match);

            $fileInfo = new SmartFileInfo($file);
            $this->singleFileProcessor->processFileInfo($fileInfo);

            $content = ltrim(file_get_contents($file), '<?php' . PHP_EOL);
            echo $content . PHP_EOL;
        }

        return 0;
    }
}
