<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ForbiddenSpreadOperatorRule\ForbiddenSpreadOperatorRuleTest
 */
final class ForbiddenSpreadOperatorRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Spread operator is not allowed.';

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [
            Closure::class,
            ArrowFunction::class,
            ClassMethod::class,
            Function_::class,
            MethodCall::class,
            StaticCall::class,
            FuncCall::class,
        ];
    }

    /**
     * @param Closure|ArrowFunction|MethodCall|StaticCall|FuncCall|ClassMethod|Function_ $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if ($node instanceof FunctionLike) {
            if (! $this->hasVariadicParam($node)) {
                return [];
            }

            return [self::ERROR_MESSAGE];
        }

        foreach ($node->args as $key => $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if (! $arg->unpack) {
                continue;
            }

            if ($key === 0) {
                // unpack args on 1st position cannot be skipped
                return [];
            }

            return [self::ERROR_MESSAGE];
        }

        return [];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
$args = [$firstValue, $secondValue];
$message =  sprintf('%s', ...$args);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$message =  sprintf('%s', $firstValue, $secondValue);
CODE_SAMPLE
            ),
        ]);
    }

    private function hasVariadicParam(Closure | ArrowFunction | ClassMethod | Function_ $node): bool
    {
        foreach ($node->params as $param) {
            if ($param->variadic) {
                return true;
            }
        }

        return false;
    }
}
