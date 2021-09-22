<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ThisType;
use Symfony\Component\HttpKernel\Kernel;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PackageBuilder\Php\TypeChecker;
use Symplify\PackageBuilder\Reflection\PrivatesCaller;
use Symplify\PHPStanRules\TypeAnalyzer\ObjectTypeAnalyzer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ForbiddenThisArgumentRule\ForbiddenThisArgumentRuleTest
 */
final class ForbiddenThisArgumentRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = '$this as argument is not allowed. Refactor method to service composition';

    /**
     * @var class-string<Kernel>[]
     */
    private const ALLOWED_PARENT_CLASSES = [Kernel::class];

    /**
     * @var class-string<PrivatesCaller>[]
     */
    private const ALLOWED_CALLER_CLASSES = [
        // workaround type
        PrivatesCaller::class,
    ];

    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private TypeChecker $typeChecker,
        private ObjectTypeAnalyzer $objectTypeAnalyzer
    ) {
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, FuncCall::class, StaticCall::class];
    }

    /**
     * @param MethodCall|FuncCall|StaticCall $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if ($this->shouldSkip($node, $scope)) {
            return [];
        }

        foreach ($node->args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            $argType = $scope->getType($arg->value);
            if (! $argType instanceof ThisType) {
                continue;
            }

            if ($this->shouldSkipClass($scope)) {
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
$this->someService->process($this, ...);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$this->someService->process($value, ...);
CODE_SAMPLE
            ),
        ]);
    }

    private function shouldSkipClass(Scope $scope): bool
    {
        $className = $this->simpleNameResolver->getClassNameFromScope($scope);
        if ($className === null) {
            return false;
        }

        return $this->typeChecker->isInstanceOf($className, self::ALLOWED_PARENT_CLASSES);
    }

    private function shouldSkip(MethodCall | FuncCall | StaticCall $node, Scope $scope): bool
    {
        if ($node instanceof MethodCall) {
            $callerType = $scope->getType($node->var);
            return $this->objectTypeAnalyzer->isObjectOrUnionOfObjectTypes($callerType, self::ALLOWED_CALLER_CLASSES);
        }

        if ($node instanceof FuncCall) {
            return $this->simpleNameResolver->isName($node, 'method_exists');
        }

        return false;
    }
}
