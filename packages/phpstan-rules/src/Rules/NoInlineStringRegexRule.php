<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\NodeAnalyzer\RegexFuncCallAnalyzer;
use Symplify\PHPStanRules\NodeAnalyzer\RegexStaticCallAnalyzer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\NoInlineStringRegexRule\NoInlineStringRegexRuleTest
 */
final class NoInlineStringRegexRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Use local named constant instead of inline string for regex to explain meaning by constant name';

    public function __construct(
        private RegexFuncCallAnalyzer $regexFuncCallAnalyzer,
        private RegexStaticCallAnalyzer $regexStaticCallAnalyzer
    ) {
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [StaticCall::class, FuncCall::class];
    }

    /**
     * @param StaticCall|FuncCall $node
     * @return mixed[]|string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if ($node instanceof FuncCall) {
            return $this->processRegexFuncCall($node);
        }

        return $this->processRegexStaticCall($node);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run($value)
    {
        return preg_match('#some_stu|ff#', $value);
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @var string
     */
    public const SOME_STUFF_REGEX = '#some_stu|ff#';

    public function run($value)
    {
        return preg_match(self::SOME_STUFF_REGEX, $value);
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    private function processRegexFuncCall(FuncCall $funcCall): array
    {
        if (! $this->regexFuncCallAnalyzer->isRegexFuncCall($funcCall)) {
            return [];
        }

        $firstArgOrVariadicPlaceholder = $funcCall->args[0];
        if (! $firstArgOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        // it's not string → good
        if (! $firstArgOrVariadicPlaceholder->value instanceof String_) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    /**
     * @return string[]
     */
    private function processRegexStaticCall(StaticCall $staticCall): array
    {
        if (! $this->regexStaticCallAnalyzer->isRegexStaticCall($staticCall)) {
            return [];
        }

        $argOrVariadicPlaceholder = $staticCall->args[1];
        if (! $argOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $secondArgValue = $argOrVariadicPlaceholder->value;

        // it's not string → good
        if (! $secondArgValue instanceof String_) {
            return [];
        }

        $regexValue = $secondArgValue->value;

        if (Strings::length($regexValue) <= 7) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }
}
