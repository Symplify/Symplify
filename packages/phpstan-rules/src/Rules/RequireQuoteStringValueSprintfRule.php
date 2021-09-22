<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\RequireQuoteStringValueSprintfRule\RequireQuoteStringValueSprintfRuleTest
 */
final class RequireQuoteStringValueSprintfRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = '"%s" in sprintf() format must be quoted';

    /**
     * @see https://regex101.com/r/OMs5yL/1
     * @var string
     */
    private const UNQUOTED_STRING_MASK_REGEX = '#(?<' . self::BEFORE_PART . '>.{1})?(%s)(?<' . self::AFTER_PART . '>.{1})?#';

    /**
     * @var string
     */
    private const BEFORE_PART = 'before';

    /**
     * @var string
     */
    private const AFTER_PART = 'after';

    public function __construct(
        private SimpleNameResolver $simpleNameResolver
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

        $args = $node->args;
        if (count($args) === 1) {
            return [];
        }

        $firstArgOrVariablePlaceholder = $args[0];
        if (! $firstArgOrVariablePlaceholder instanceof Arg) {
            return [];
        }

        $format = $firstArgOrVariablePlaceholder->value;
        if (! $format instanceof String_) {
            return [];
        }

        if (! $this->doesContainBareStringMask($format->value)) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        echo sprintf('%s value', $variable);
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        echo sprintf('"%s" value', $variable);
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    private function doesContainBareStringMask(string $content): bool
    {
        $matches = Strings::match($content, self::UNQUOTED_STRING_MASK_REGEX);
        if ($matches === null) {
            return false;
        }

        $before = $matches[self::BEFORE_PART] ?? ' ';
        if ($before === '') {
            $before = ' ';
        }
        $after = $matches[self::AFTER_PART] ?? ' ';
        if ($after === '') {
            $after = ' ';
        }

        if ($before !== $after) {
            return false;
        }

        if (in_array($before, ["'", '"'], true)) {
            return false;
        }

        return $before === ' ';
    }
}
