<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use Symfony\Component\Console\Input\InputOption;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\RequireNewArgumentConstantRule\RequireNewArgumentConstantRuleTest
 */
final class RequireNewArgumentConstantRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'New expression argument on position %d must use constant over value';

    /**
     * @var array<class-string, int[]>
     */
    private array $constantArgByNewByType = [];

    /**
     * @param array<class-string, int[]> $constantArgByNewByType
     */
    public function __construct(array $constantArgByNewByType = [])
    {
        $this->constantArgByNewByType = $constantArgByNewByType;
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    /**
     * @param New_ $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        $class = $node->class;
        if (! $class instanceof FullyQualified) {
            return [];
        }

        $className = $class->toString();
        if (! array_key_exists($className, $this->constantArgByNewByType)) {
            return [];
        }

        $args = $node->args;
        $positions = $this->constantArgByNewByType[$className];

        foreach ($positions as $position) {
            $argOrVariadicPlaceholder = $args[$position];
            if (! $argOrVariadicPlaceholder instanceof Arg) {
                continue;
            }

            if (! $argOrVariadicPlaceholder->value instanceof ClassConstFetch) {
                return [sprintf(self::ERROR_MESSAGE, $position)];
            }
        }

        return [];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
use Symfony\Component\Console\Input\InputOption;

$inputOption = new InputOption('name', null, 2);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use Symfony\Component\Console\Input\InputOption;

$inputOption = new InputOption('name', null, InputOption::VALUE_REQUIRED);
CODE_SAMPLE
                ,
                [
                    'constantArgByNewByType' => [
                        InputOption::class => [2],
                    ],
                ]
            ),
        ]);
    }
}
