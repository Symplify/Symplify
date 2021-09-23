<?php

declare(strict_types=1);

namespace Symplify\Astral\NodeTraverser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use Symplify\Astral\NodeVisitor\CallableNodeVisitor;

final class SimpleCallableNodeTraverser
{
    /**
     * @param Node|Node[]|null $nodes
     */
    public function traverseNodesWithCallable(Node | array | null $nodes, callable $callable): void
    {
        if ($nodes === null) {
            return;
        }

        if ($nodes === []) {
            return;
        }

        if (! is_array($nodes)) {
            $nodes = [$nodes];
        }

        $nodeTraverser = new NodeTraverser();
        $callableNodeVisitor = new CallableNodeVisitor($callable);
        $nodeTraverser->addVisitor($callableNodeVisitor);
        $nodeTraverser->traverse($nodes);
    }
}
