<?php

declare(strict_types=1);

namespace Symplify\EasyCI\ActiveClass;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use Symplify\EasyCI\ActiveClass\NodeDecorator\FullyQualifiedNameNodeDecorator;
use Symplify\EasyCI\ActiveClass\NodeVisitor\ClassNameNodeVisitor;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Symplify\EasyCI\Tests\ActiveClass\ClassNameResolver\ClassNameResolverTest
 */
final class ClassNameResolver
{
    public function __construct(
        private Parser $parser,
        private FullyQualifiedNameNodeDecorator $fullyQualifiedNameNodeDecorator
    ) {
    }

    public function resolveFromFromFileInfo(SmartFileInfo $phpFileInfo): ?string
    {
        $stmts = $this->parser->parse($phpFileInfo->getContents());
        if ($stmts === null) {
            return null;
        }

        $this->fullyQualifiedNameNodeDecorator->decorate($stmts);

        $classNameNodeVisitor = new ClassNameNodeVisitor();
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($classNameNodeVisitor);
        $nodeTraverser->traverse($stmts);

        return $classNameNodeVisitor->getClassName();
    }
}
