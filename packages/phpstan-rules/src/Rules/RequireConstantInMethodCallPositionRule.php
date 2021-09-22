<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use SomeType;
use Symplify\PHPStanRules\Matcher\PositionMatcher;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\RequireConstantInMethodCallPositionRule\RequireConstantInMethodCallPositionRuleTest
 */
final class RequireConstantInMethodCallPositionRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Parameter argument on position %d must use %s constant';

    /**
     * @var array<class-string, mixed[]>
     */
    private array $requiredLocalConstantInMethodCall = [];

    /**
     * @var array<class-string, mixed[]>
     */
    private array $requiredExternalConstantInMethodCall = [];

    /**
     * @param array<class-string, mixed[]> $requiredLocalConstantInMethodCall
     * @param array<class-string, mixed[]> $requiredExternalConstantInMethodCall
     */
    public function __construct(
        private PositionMatcher $positionMatcher,
        array $requiredLocalConstantInMethodCall = [],
        array $requiredExternalConstantInMethodCall = []
    ) {
        $this->requiredLocalConstantInMethodCall = $requiredLocalConstantInMethodCall;
        $this->requiredExternalConstantInMethodCall = $requiredExternalConstantInMethodCall;
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
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $errorMessagesLocal = $this->getErrorMessages(
            $node,
            $scope,
            true,
            $this->requiredLocalConstantInMethodCall,
            'local'
        );

        $errorMessagesExternal = $this->getErrorMessages(
            $node,
            $scope,
            false,
            $this->requiredExternalConstantInMethodCall,
            'external'
        );

        return array_merge($errorMessagesLocal, $errorMessagesExternal);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function someMethod(SomeType $someType)
    {
        $someType->someMethod('hey');
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    private const HEY = 'hey'

    public function someMethod(SomeType $someType)
    {
        $someType->someMethod(self::HEY);
    }
}
CODE_SAMPLE
                ,
                [
                    'requiredLocalConstantInMethodCall' => [
                        SomeType::class => [
                            'someMethod' => [0],
                        ],
                    ],
                ]
            ),
        ]);
    }

    /**
     * @param mixed[] $config
     * @return string[]
     */
    private function getErrorMessages(
        MethodCall $methodCall,
        Scope $scope,
        bool $isLocalConstant,
        array $config,
        string $messageVar
    ): array {
        /** @var Identifier $name */
        $name = $methodCall->name;
        $methodName = (string) $name;
        $errorMessages = [];

        /** @var class-string $type */
        foreach ($config as $type => $positionsByMethods) {
            $positions = $this->positionMatcher->matchPositions(
                $methodCall,
                $scope,
                $type,
                $positionsByMethods,
                $methodName
            );
            if ($positions === null) {
                continue;
            }

            foreach ($methodCall->args as $key => $arg) {
                if (! $arg instanceof Arg) {
                    continue;
                }

                if ($this->shouldSkipArg($key, $positions, $arg, $isLocalConstant)) {
                    continue;
                }

                $errorMessages[] = sprintf(self::ERROR_MESSAGE, $key, $messageVar);
            }
        }

        return $errorMessages;
    }

    /**
     * @param int[] $positions
     */
    private function shouldSkipArg(int $key, array $positions, Arg $arg, bool $isLocalConstant): bool
    {
        if (! in_array($key, $positions, true)) {
            return true;
        }

        if ($arg->value instanceof Variable) {
            return true;
        }

        if (! $arg->value instanceof ClassConstFetch) {
            return false;
        }

        return $isLocalConstant
            ? $arg->value->class instanceof Name
            : $arg->value->class instanceof FullyQualified;
    }
}
