<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use SimpleXMLElement;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PackageBuilder\Matcher\ArrayStringAndFnMatcher;
use Symplify\PHPStanRules\Formatter\RequiredWithMessageFormatter;
use Symplify\PHPStanRules\TypeAnalyzer\ObjectTypeAnalyzer;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ForbiddenFuncCallRule\ForbiddenFuncCallRuleTest
 */
final class ForbiddenFuncCallRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Function "%s()" cannot be used/left in the code';

    /**
     * @param string[]|array<string|int, string> $forbiddenFunctions
     */
    public function __construct(
        private array $forbiddenFunctions,
        private ArrayStringAndFnMatcher $arrayStringAndFnMatcher,
        private SimpleNameResolver $simpleNameResolver,
        private ObjectTypeAnalyzer $objectTypeAnalyzer,
        private RequiredWithMessageFormatter $requiredWithMessageFormatter,
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
        $funcName = $this->simpleNameResolver->getName($node);
        if ($funcName === null) {
            return [];
        }

        $requiredWithMessages = $this->requiredWithMessageFormatter->normalizeConfig($this->forbiddenFunctions);
        foreach ($requiredWithMessages as $requiredWithMessage) {
            if (! $this->arrayStringAndFnMatcher->isMatch($funcName, [$requiredWithMessage->getRequired()])) {
                continue;
            }

            // special cases
            if ($this->shouldAllowSpecialCase($node, $scope, $funcName)) {
                continue;
            }

            if ($requiredWithMessage->getMessage() === null) {
                $errorMessage = sprintf(self::ERROR_MESSAGE, $funcName);
            } else {
                $errorMessage = sprintf(self::ERROR_MESSAGE . ': ' . $requiredWithMessage->getMessage(), $funcName);
            }

            return [$errorMessage];
        }

        return [];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    return eval('...');
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    return echo '...';
}
CODE_SAMPLE
            ,
                [
                    'forbiddenFunctions' => ['eval'],
                ]
            ),
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    dump('hello world');
    return true;
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    return true;
}
CODE_SAMPLE
            ,
                [
                    'forbiddenFunctions' => [
                        'dump' => 'seems you missed some debugging function',
                    ],
                ]
            ),
        ]);
    }

    private function shouldAllowSpecialCase(FuncCall $funcCall, Scope $scope, string $functionName): bool
    {
        if ($functionName !== 'property_exists') {
            return false;
        }

        $argOrVariadicPlaceholder = $funcCall->args[0];
        if (! $argOrVariadicPlaceholder instanceof Arg) {
            return false;
        }

        $firstArgValue = $argOrVariadicPlaceholder->value;
        $firstArgType = $scope->getType($firstArgValue);

        return $this->objectTypeAnalyzer->isObjectOrUnionOfObjectType($firstArgType, SimpleXMLElement::class);
    }
}
