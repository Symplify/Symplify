<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Twig;

use Symplify\SmartFileSystem\SmartFileSystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\ModuleNode;
use Twig\Source;

final class TwigNodeParser
{
    public function __construct(
        private SmartFileSystem $smartFileSystem
    ) {
    }

    /**
     * @return ModuleNode<\Twig\Node\Node>
     */
    public function parseFilePath(string $filePath): ModuleNode
    {
        $fileContent = $this->smartFileSystem->readFile($filePath);

        $arrayLoader = new ArrayLoader([
            $filePath => $fileContent,
        ]);

        $environment = new Environment($arrayLoader);
        $tokenStream = $environment->tokenize(new Source($fileContent, $filePath));

        return $environment->parse($tokenStream);
    }
}
