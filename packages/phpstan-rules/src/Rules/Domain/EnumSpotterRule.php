<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules\Domain;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\TypeWithClassName;
use PHPUnit\Framework\TestCase;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PHPStanRules\NodeAnalyzer\Duplicates\DuplicatedStringArgValueResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\PHPStanRules\ValueObject\Duplicates\DuplicatedStringArg;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\Domain\EnumSpotterRule\EnumSpotterRuleTest
 */
final class EnumSpotterRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'The string value "%s" is repeated %d times. Refactor to enum to avoid typos and make clear allowed values';

    /**
     * @var string[]
     */
    private const ALLOWED_STRING_VALUES = ['this', 'config.php'];

    /**
     * @var int
     */
    private const MIN_STRING_LENGTH = 3;

    /**
     * @var array<string, string[]>
     */
    private array $stringValuesByUniqueId = [];

    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private DuplicatedStringArgValueResolver $duplicatedStringArgValueResolver,
        private int $repeatedCountThreshold = 5,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
$this->addFlash('info', 'Some message');
$this->addFlash('info', 'Another message');
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$this->addFlash(FlashType::INFO, 'Some message');
$this->addFlash(FlashType::INFO, 'Another message');
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
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if ($this->shouldSkip($node, $scope)) {
            return [];
        }

        $methodName = $this->simpleNameResolver->getName($node->name);
        if ($methodName === null) {
            return [];
        }

        $callerType = $scope->getType($node->var);
        if (! $callerType instanceof TypeWithClassName) {
            return [];
        }

        $this->collectArgStringValues($node, $callerType, $methodName, self::MIN_STRING_LENGTH);

        $duplicatedStringArg = $this->duplicatedStringArgValueResolver->resolve(
            $this->stringValuesByUniqueId,
            $this->repeatedCountThreshold
        );

        if (! $duplicatedStringArg instanceof DuplicatedStringArg) {
            return [];
        }

        // method argument is repeating value, maybe
        $errorMessage = sprintf(
            self::ERROR_MESSAGE,
            $duplicatedStringArg->getValue(),
            $duplicatedStringArg->getCount()
        );

        return [$errorMessage];
    }

    private function collectArgStringValues(
        MethodCall $methodCall,
        TypeWithClassName $typeWithClassName,
        string $methodName,
        int $minLength
    ): void {
        foreach ($methodCall->args as $position => $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if (! $arg->value instanceof String_) {
                continue;
            }

            $argumentStringValue = $arg->value->value;

            // values with spaces will not be enums probably
            if (str_contains($argumentStringValue, ' ')) {
                continue;
            }

            if (strlen($argumentStringValue) < $minLength) {
                continue;
            }

            // skipped names
            if (in_array($argumentStringValue, self::ALLOWED_STRING_VALUES, true)) {
                continue;
            }

            $uniqueId = $typeWithClassName->getClassName() . $methodName . $position;
            $this->stringValuesByUniqueId[$uniqueId][] = $argumentStringValue;
        }
    }

    private function shouldSkip(MethodCall $methodCall, Scope $scope): bool
    {
        if ($methodCall->args === []) {
            return true;
        }

        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            return true;
        }

        // skip tests
        return $classReflection->isSubclassOf(TestCase::class);
    }
}
