<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * @see \Symplify\CodingStandard\Tests\Rules\NoParentMethodCallOnNoOverrideProcessRule\NoParentMethodCallOnNoOverrideProcessRuleTest
 */
final class NoParentMethodCallOnNoOverrideProcessRule implements Rule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Do not call parent method if no override process';

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        /** @var Name $name */
        $name = $node->class;
        $classCaller = $name->parts[0];

        if ($classCaller !== 'parent') {
            return [];
        }

        /** @var ClassMethod $classMethod */
        $classMethod = $node
            ->getAttribute(AttributeKey::PARENT_NODE)
            ->getAttribute(AttributeKey::PARENT_NODE);

        if (! $classMethod instanceof ClassMethod) {
            return [];
        }

        if ((string) $classMethod->name !== $node->name->toString()) {
            return [];
        }

        /** @var Stmt[] $stmts */
        $stmts = $this->nodeFinder->findInstanceOf((array) $classMethod->getStmts(), Stmt::class);
        if (count($stmts) === 1) {
            return [self::ERROR_MESSAGE];
        }

        $countStmts = 0;
        foreach ($stmts as $stmt) {
            // ensure empty statement not counted
            if ($stmt instanceof Nop) {
                continue;
            }

            ++$countStmts;
        }

        if ($countStmts === 1) {
            return [self::ERROR_MESSAGE];
        }

        return [];
    }
}
