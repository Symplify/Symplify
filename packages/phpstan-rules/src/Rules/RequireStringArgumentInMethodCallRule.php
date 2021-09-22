<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Analyser\Scope;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PHPStanRules\TypeAnalyzer\ObjectTypeAnalyzer;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Useful for prefixed phar bulid, to keep original references to class un-prefixed
 *
 * Basically inversion of this rule:
 *
 * @see https://github.com/symplify/symplify/tree/master/packages/coding-standard#defined-method-argument-should-be-always-constant-value
 *
 * @see \Symplify\PHPStanRules\Tests\Rules\RequireStringArgumentInMethodCallRule\RequireStringArgumentInMethodCallRuleTest
 */
final class RequireStringArgumentInMethodCallRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Use quoted string in method call "%s()" argument on position %d instead of "::class. It prevent scoping of the class in building prefixed package.';

    /**
     * @var array<string, array<string, array<int>>>
     */
    private array $stringArgPositionByMethodByType = [];

    /**
     * @param array<string, array<string, array<int>>> $stringArgPositionByMethodByType
     */
    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private ObjectTypeAnalyzer $objectTypeAnalyzer,
        array $stringArgPositionByMethodByType = []
    ) {
        $this->stringArgPositionByMethodByType = $stringArgPositionByMethodByType;
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
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        $methodCallName = $this->simpleNameResolver->getName($node->name);
        if ($methodCallName === null) {
            return [];
        }

        $errorMessages = [];

        foreach ($this->stringArgPositionByMethodByType as $type => $positionsByMethods) {
            $positions = $this->matchPositions($node, $scope, $type, $positionsByMethods, $methodCallName);
            if ($positions === []) {
                continue;
            }

            foreach ($node->args as $key => $arg) {
                if ($this->shouldSkipArg($key, $positions, $arg)) {
                    continue;
                }

                $errorMessages[] = sprintf(self::ERROR_MESSAGE, $methodCallName, $key);
            }
        }

        return $errorMessages;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class AnotherClass
{
    public function run(SomeClass $someClass)
    {
        $someClass->someMethod(YetAnotherClass:class);
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class AnotherClass
{
    public function run(SomeClass $someClass)
    {
        $someClass->someMethod('YetAnotherClass'');
    }
}
CODE_SAMPLE
                ,
                [
                    'stringArgPositionByMethodByType' => [
                        'SomeClass' => [
                            'someMethod' => [0],
                        ],
                    ],
                ]
            ),
        ]);
    }

    /**
     * @param array<string, array<int>> $positionsByMethods
     * @return int[]
     */
    private function matchPositions(
        MethodCall $methodCall,
        Scope $scope,
        string $desiredType,
        array $positionsByMethods,
        string $methodName
    ): array {
        if (! $this->isNodeVarType($methodCall, $scope, $desiredType)) {
            return [];
        }

        return $positionsByMethods[$methodName] ?? [];
    }

    /**
     * @param int[] $positions
     */
    private function shouldSkipArg(int $key, array $positions, Arg|VariadicPlaceholder $arg): bool
    {
        if (! $arg instanceof Arg) {
            return true;
        }

        if (! in_array($key, $positions, true)) {
            return true;
        }

        if ($arg->value instanceof String_) {
            return true;
        }

        /** @var ClassConstFetch $classConstFetch */
        $classConstFetch = $arg->value;

        return ! $this->simpleNameResolver->isName($classConstFetch->name, 'class');
    }

    private function isNodeVarType(MethodCall $methodCall, Scope $scope, string $desiredType): bool
    {
        $methodVarType = $scope->getType($methodCall->var);
        return $this->objectTypeAnalyzer->isObjectOrUnionOfObjectType($methodVarType, $desiredType);
    }
}
