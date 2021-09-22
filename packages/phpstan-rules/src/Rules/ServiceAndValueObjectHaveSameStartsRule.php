<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PHPStanRules\Symfony\NodeAnalyzer\SymfonyConfigMethodCallAnalyzer;
use Symplify\PHPStanRules\Symfony\NodeAnalyzer\SymfonyConfigRectorValueObjectResolver;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ServiceAndValueObjectHaveSameStartsRule\ServiceAndValueObjectHaveSameStartsRuleTest
 */
final class ServiceAndValueObjectHaveSameStartsRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Value object "%s" should be named "%s" instead to respect used service';

    /**
     * @param string[] $classSuffixes
     */
    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private SymfonyConfigRectorValueObjectResolver $symfonyConfigRectorValueObjectResolver,
        private SymfonyConfigMethodCallAnalyzer $symfonyConfigMethodCallAnalyzer,
        private array $classSuffixes
    ) {
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
        if (! $this->symfonyConfigMethodCallAnalyzer->isServicesSet($node, $scope)) {
            return [];
        }

        $shortClass = $this->resolveSetMethodCallShortClass($node);
        if ($shortClass === null) {
            return [];
        }

        $valueObjectShortClass = $this->resolveValueObjectShortClass($node);
        if ($valueObjectShortClass === null) {
            return [];
        }

        foreach ($this->classSuffixes as $classSuffix) {
            if (! \str_ends_with($shortClass, $classSuffix)) {
                continue;
            }

            $expectedValueObjectShortClass = Strings::substring($shortClass, 0, -Strings::length($classSuffix));
            if ($expectedValueObjectShortClass === $valueObjectShortClass) {
                return [];
            }

            $errorMessage = sprintf(self::ERROR_MESSAGE, $valueObjectShortClass, $expectedValueObjectShortClass);
            return [$errorMessage];
        }

        return [];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Make specific service suffix to use similar value object names for configuring in Symfony configs',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SomeRector::class)
        ->call('configure', [[
            new Another()
        ]]);
};
CODE_SAMPLE
,
                    <<<'CODE_SAMPLE'
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SomeRector::class)
        ->call('configure', [[
            new Some()
        ]]);
};
CODE_SAMPLE
,
                    [
                        'classSuffixes' => ['Rector'],
                    ]
                ),
            ]
        );
    }

    private function resolveSetMethodCallShortClass(MethodCall $methodCall): ?string
    {
        $firstArgOrVariablePlaceholder = $methodCall->args[0];
        if (! $firstArgOrVariablePlaceholder instanceof Arg) {
            return null;
        }

        $setFirstArgValue = $firstArgOrVariablePlaceholder->value;
        if (! $setFirstArgValue instanceof ClassConstFetch) {
            return null;
        }

        $rectorClass = $this->simpleNameResolver->getName($setFirstArgValue->class);
        if ($rectorClass === null) {
            return null;
        }

        return $this->simpleNameResolver->resolveShortName($rectorClass);
    }

    private function resolveValueObjectShortClass(MethodCall $methodCall): ?string
    {
        $valueObjectClass = $this->symfonyConfigRectorValueObjectResolver->resolveFromSetMethodCall($methodCall);
        if ($valueObjectClass === null) {
            return null;
        }

        // is it implements interface, it can have many forms
        $interfaces = class_implements($valueObjectClass);
        if ($interfaces !== []) {
            return null;
        }

        return $this->simpleNameResolver->resolveShortName($valueObjectClass);
    }
}
