<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ForbiddenComplexFuncCallRule\ForbiddenComplexFuncCallRuleTest
 */
final class ForbiddenComplexFuncCallRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Do not use "%s" function with complex content, make it more readable with extracted method or single-line statement';

    /**
     * @var string[]
     */
    private array $forbiddenComplexFunctions = [];

    /**
     * @param string[] $forbiddenComplexFunctions
     */
    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        array $forbiddenComplexFunctions = [],
        private int $maximumStmtCount = 2
    ) {
        $this->forbiddenComplexFunctions = $forbiddenComplexFunctions;
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
        if (! $this->simpleNameResolver->isNames($node, $this->forbiddenComplexFunctions)) {
            return [];
        }

        if (! isset($node->args[1])) {
            return [];
        }

        $firstArgOrVariadicPlaceholder = $node->args[1];
        if (! $firstArgOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $secondArgValue = $firstArgOrVariadicPlaceholder->value;
        if (! $secondArgValue instanceof Closure) {
            return [];
        }

        if (count($secondArgValue->stmts) < $this->maximumStmtCount) {
            return [];
        }

        $funcCallName = $this->simpleNameResolver->getName($node);
        return [sprintf(self::ERROR_MESSAGE, $funcCallName)];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
$filteredElements = array_filter($elemnets, function ($item) {
    if ($item) {
        return true;
    }

    if ($item === null) {
        return true;
    }

    return false;
};
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$filteredElements = array_filter($elemnets, function ($item) {
    return $item instanceof KeepItSimple;
};
CODE_SAMPLE
                ,
                [
                    'forbiddenComplexFunctions' => ['array_filter'],
                    'maximumStmtCount' => 2,
                ]
            ),
        ]);
    }
}
