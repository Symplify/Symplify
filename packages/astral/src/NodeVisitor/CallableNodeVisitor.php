<?php

declare(strict_types=1);

namespace Symplify\Astral\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitorAbstract;

final class CallableNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var callable
     */
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @return int|Node|null
     */
    public function enterNode(Node $node)
    {
        $originalNode = $node;

        $callable = $this->callable;

        /** @var int|Node|null $newNode */
        $newNode = $callable($node);

        if ($originalNode instanceof Stmt && $newNode instanceof Expr) {
            return new Expression($newNode);
        }

        return $newNode;
    }
}
