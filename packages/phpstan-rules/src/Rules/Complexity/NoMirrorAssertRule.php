<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PHPStanRules\NodeAnalyzer\PHPUnit\TestAnalyzer;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\Complexity\NoMirrorAssertRule\NoMirrorAssertRuleTest
 */
final class NoMirrorAssertRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'The assert is tautology that compares to itself. Fix it to different values';

    public function __construct(
        private TestAnalyzer $testAnalyzer,
        private Standard $standard,
        private SimpleNameResolver $simpleNameResolver
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
use PHPUnit\Framework\TestCase;

final class AssertMirror extends TestCase
{
    public function test()
    {
        $this->assertSame(1, 1);
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use PHPUnit\Framework\TestCase;

final class AssertMirror extends TestCase
{
    public function test()
    {
        $value = 200;
        $this->assertSame(1, $value);
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     * @return string[]|RuleError[]
     */
    public function process(Node $node, Scope $scope): array
    {
        // allow in test case methods, possibly to compare reults
        if (! $this->testAnalyzer->isInTestClassMethod($scope, $node)) {
            return [];
        }

        // compare 1st and 2nd value
        if (count($node->args) < 2) {
            return [];
        }

        // only "assert*" methods
        if (! $this->simpleNameResolver->isName($node->name, 'assert*')) {
            return [];
        }

        $firstArgOrVariadicPlaceholder = $node->args[0];
        if (! $firstArgOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $firstArgValue = $firstArgOrVariadicPlaceholder->value;

        $secondArgOrVariadicPlaceholder = $node->args[1];
        if (! $secondArgOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $secondArgValue = $secondArgOrVariadicPlaceholder->value;

        $firstArgValueContent = $this->standard->prettyPrintExpr($firstArgValue);
        $secondArgValueContent = $this->standard->prettyPrintExpr($secondArgValue);

        if ($firstArgValueContent !== $secondArgValueContent) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }
}
