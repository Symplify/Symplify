<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\NoFuncCallInMethodCallRule\NoFuncCallInMethodCallRuleTest
 */
final class NoFuncCallInMethodCallRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Separate function "%s()" in method call to standalone row to improve readability';

    /**
     * @var string[]
     */
    private const ALLOWED_FUNC_CALL_NAMES = ['getcwd', 'sys_get_temp_dir'];

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        $messages = [];

        foreach ($node->args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if (! $arg->value instanceof FuncCall) {
                continue;
            }

            $funcCallName = $this->resolveFuncCallName($arg);

            if (\str_contains($funcCallName, '\\')) {
                continue;
            }

            if (in_array($funcCallName, self::ALLOWED_FUNC_CALL_NAMES, true)) {
                continue;
            }

            $messages[] = sprintf(self::ERROR_MESSAGE, $funcCallName);
        }

        return $messages;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run($value): void
    {
        $this->someMethod(strlen('fooo'));
    }

    // ...
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run($value): void
    {
        $fooLength = strlen('fooo');
        $this->someMethod($fooLength);
    }

    // ...
}
CODE_SAMPLE
            ),
        ]);
    }

    private function resolveFuncCallName(Arg $arg): string
    {
        /** @var FuncCall $funcCall */
        $funcCall = $arg->value;
        if ($funcCall->name instanceof Expr) {
            return '*dynamic*';
        }

        return (string) $funcCall->name;
    }
}
