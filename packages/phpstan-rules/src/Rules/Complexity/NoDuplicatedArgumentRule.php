<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Symplify\PHPStanRules\NodeAnalyzer\ScalarValueResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\Complexity\NoDuplicatedArgumentRule\NoDuplicatedArgumentRuleTest
 */
final class NoDuplicatedArgumentRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'This call has duplicate argument';

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private ScalarValueResolver $scalarValueResolver
    ) {
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class, FuncCall::class];
    }

    /**
     * @param MethodCall|StaticCall|FuncCall $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if ($this->shouldSkip($node, $scope)) {
            return [];
        }

        $countValues = $this->scalarValueResolver->resolveValuesCountFromArgs($node->args, $scope);

        // each of kind
        if ($countValues === []) {
            return [];
        }

        $maxCountValues = max($countValues);
        if ($maxCountValues === 1) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            self::ERROR_MESSAGE,
            [new CodeSample(
                <<<'CODE_SAMPLE'
function run($one, $one);
CODE_SAMPLE
    ,
                <<<'CODE_SAMPLE'
function run($one, $two);
CODE_SAMPLE
            )]
        );
    }

    private function shouldSkip(StaticCall|MethodCall|FuncCall $expr, Scope $scope): bool
    {
        if (\count((array) $expr->args) < 2) {
            return true;
        }

        if ($expr instanceof FuncCall) {
            if (! $expr->name instanceof Name) {
                return true;
            }

            if (! $this->reflectionProvider->hasFunction($expr->name, $scope)) {
                return false;
            }

            // skip native functions
            $functionReflection = $this->reflectionProvider->getFunction($expr->name, $scope);
            return $functionReflection->isBuiltin();
        }

        return false;
    }
}
