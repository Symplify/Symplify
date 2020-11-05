<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\ValueObject\PHPStanAttributeKey;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\CheckConstantExpressionDefinedInConstructOrSetupRule\CheckConstantExpressionDefinedInConstructOrSetupRuleTest
 */
final class CheckConstantExpressionDefinedInConstructOrSetupRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Constant expression should only defined in __construct() or setUp()';

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct(NodeFinder $nodeFinder)
    {
        $this->nodeFinder = $nodeFinder;
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Assign::class];
    }

    /**
     * @param Assign $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        $classMethod = $this->resolveCurrentClassMethod($node);
        if ($classMethod === null) {
            return [];
        }

        $parent = $node->getAttribute(PHPStanAttributeKey::PARENT);
        if ($this->isNotInsideClassMethodDirectly($parent) || $this->isUsedInNextStatement($node, $parent)) {
            return [];
        }

        if (in_array(strtolower((string) $classMethod->name), ['__construct', 'setup'], true)) {
            return [];
        }

        if ($node->expr instanceof Concat && $node->expr->left instanceof MagicConst) {
            if ($node->expr->right instanceof MethodCall) {
                return [];
            }

            return [self::ERROR_MESSAGE];
        }

        if (! $node->expr instanceof ClassConstFetch) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    public function isNotInsideClassMethodDirectly(Node $node): bool
    {
        $parentStatement = $node->getAttribute(PHPStanAttributeKey::PARENT);
        return ! $parentStatement instanceof ClassMethod;
    }

    private function isUsedInNextStatement(Assign $assign, Node $node): bool
    {
        $var = $assign->var;
        $varClass = get_class($var);
        $next = $node->getAttribute(PHPStanAttributeKey::NEXT);

        while ($next) {
            $nextVars = $this->nodeFinder->findInstanceOf($next, $varClass);
            foreach ($nextVars as $nextVar) {
                if (property_exists($nextVar, 'name') && property_exists(
                    $var,
                    'name'
                ) && $nextVar->name === $var->name) {
                    return true;
                }
            }

            $next = $next->getAttribute(PHPStanAttributeKey::NEXT);
        }

        return false;
    }
}
