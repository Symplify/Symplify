<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PHPStanRules\ParentGuard\ParentClassMethodGuard;
use Symplify\PHPStanRules\TypeAnalyzer\ForbiddenAllowedTypeAnalyzer;
use Symplify\PHPStanRules\TypeResolver\NullableTypeResolver;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ForbiddenNullableParameterRule\ForbiddenNullableParameterRuleTest
 */
final class ForbiddenNullableParameterRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Parameter "%s" cannot be nullable';

    /**
     * @var class-string[]
     */
    private array $forbiddenTypes = [];

    /**
     * @var class-string[]
     */
    private array $allowedTypes = [];

    /**
     * @param class-string[] $forbiddenTypes
     * @param class-string[] $allowedTypes
     */
    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private NullableTypeResolver $nullableTypeResolver,
        private ParentClassMethodGuard $parentClassMethodGuard,
        private ForbiddenAllowedTypeAnalyzer $forbiddenAllowedTypeAnalyzer,
        array $forbiddenTypes = [],
        array $allowedTypes = []
    ) {
        $this->forbiddenTypes = $forbiddenTypes;
        $this->allowedTypes = $allowedTypes;
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class, Closure::class];
    }

    /**
     * @param ClassMethod|Function_|Closure $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if ($this->parentClassMethodGuard->isFunctionLikeProtected($node, $scope)) {
            return [];
        }

        $errorMessages = [];
        foreach ($node->params as $param) {
            $paramType = $this->matchNullableParamType($param);
            if ($paramType === null) {
                continue;
            }

            $normalType = $this->nullableTypeResolver->resolveNormalType($paramType);
            if ($normalType === null) {
                continue;
            }

            if ($this->forbiddenAllowedTypeAnalyzer->shouldSkip(
                $normalType,
                $this->forbiddenTypes,
                $this->allowedTypes
            )) {
                continue;
            }

            $paramName = $this->simpleNameResolver->getName($param->var);
            $errorMessages[] = sprintf(self::ERROR_MESSAGE, $paramName);
        }

        return $errorMessages;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
use PhpParser\Node;

class SomeClass
{
    public function run(?Node $node = null): void
    {
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use PhpParser\Node;

class SomeClass
{
    public function run(Node $node): void
    {
    }
}
CODE_SAMPLE
                ,
                [
                    'forbiddenTypes' => [Node::class],
                    'allowedTypes' => [String_::class],
                ]
            ),
        ]);
    }

    /**
     * @return Identifier|Name|ComplexType
     */
    private function matchNullableParamType(Param $param): ?Node
    {
        $paramType = $param->type;
        if ($paramType === null) {
            return null;
        }

        if (! $this->isNullableParam($param)) {
            return null;
        }

        return $paramType;
    }

    private function isNullableParam(Param $param): bool
    {
        if ($param->type instanceof NullableType) {
            return true;
        }

        if ($param->default === null) {
            return false;
        }

        if (! $param->default instanceof ConstFetch) {
            return false;
        }

        return $this->simpleNameResolver->isName($param->default, 'null');
    }
}
