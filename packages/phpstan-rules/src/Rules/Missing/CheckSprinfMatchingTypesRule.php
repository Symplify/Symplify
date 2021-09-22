<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules\Missing;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantStringType;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PHPStanRules\NodeAnalyzer\SprintfSpecifierTypeResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\PHPStanRules\TypeAnalyzer\MatchingTypeAnalyzer;
use Symplify\PHPStanRules\TypeResolver\ArgTypeResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\Missing\CheckSprinfMatchingTypesRule\CheckSprinfMatchingTypesRuleTest
 *
 * @inspiration by https://github.com/phpstan/phpstan-src/blob/master/src/Rules/Functions/PrintfParametersRule.php
 */
final class CheckSprinfMatchingTypesRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'sprintf() call mask types does not match provided arguments types';

    /**
     * @var string
     */
    private const SPECIFIERS = '[bcdeEfFgGosuxX%s]';

    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private SprintfSpecifierTypeResolver $sprintfSpecifierTypeResolver,
        private MatchingTypeAnalyzer $matchingTypeAnalyzer,
        private ArgTypeResolver $argTypeResolver,
    ) {
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /**
     * @param FuncCall $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if (! $this->simpleNameResolver->isName($node, 'sprintf')) {
            return [];
        }

        $argOrVariadicPlaceholder = $node->args[0];
        if (! $argOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $formatArgType = $scope->getType($argOrVariadicPlaceholder->value);
        if (! $formatArgType instanceof ConstantStringType) {
            return [];
        }

        $specifiersMatches = $this->resolveSpecifierMatches($formatArgType);

        $argTypes = $this->argTypeResolver->resolveArgTypesWithoutFirst($node, $scope);
        $expectedTypesByPosition = $this->sprintfSpecifierTypeResolver->resolveFromSpecifiers($specifiersMatches);

        // miss-matching count, handled by native PHPStan rule
        if (count($argTypes) !== count($expectedTypesByPosition)) {
            return [];
        }

        foreach ($argTypes as $key => $argType) {
            $expectedTypes = $expectedTypesByPosition[$key];

            if ($this->matchingTypeAnalyzer->isTypeMatchingExpectedTypes($argType, $expectedTypes)) {
                continue;
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
echo sprintf('My name is %s and I have %d children', 10, 'Tomas');

CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
echo sprintf('My name is %s and I have %d children', 'Tomas', 10);
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @see https://github.com/phpstan/phpstan-src/blob/e10a7aac373e8b6f21b430034fc693300c2bbb69/src/Rules/Functions/PrintfParametersRule.php#L105-L115
     * @return string[]
     */
    private function resolveSpecifierMatches(ConstantStringType $constantStringType): array
    {
        $value = $constantStringType->getValue();
        $pattern = '#%(?:(?<position>\d+)\$)?[-+]?(?:[ 0]|(?:\'[^%]))?-?\d*(?:\.\d*)?' . self::SPECIFIERS . '#';

        $allMatches = Strings::matchAll($value, $pattern);
        return Arrays::flatten($allMatches);
    }
}
