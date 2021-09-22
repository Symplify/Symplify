<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\Nette\Latte\NodeAnalyzer\MissingLatteTemplateRenderVariableResolver;
use Symplify\PHPStanRules\Nette\NodeAnalyzer\TemplateRenderAnalyzer;
use Symplify\PHPStanRules\NodeAnalyzer\PathResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Nette\Tests\Rules\NoNetteRenderMissingVariableRule\NoNetteRenderMissingVariableRuleTest
 */
final class NoNetteRenderMissingVariableRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Passed "%s" variable that are not used in the template';

    public function __construct(
        private TemplateRenderAnalyzer $templateRenderAnalyzer,
        private PathResolver $pathResolver,
        private MissingLatteTemplateRenderVariableResolver $missingLatteTemplateRenderVariableResolver
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
        if (! $this->templateRenderAnalyzer->isNetteTemplateRenderMethodCall($node, $scope)) {
            return [];
        }

        if (count($node->args) < 1) {
            return [];
        }

        $argOrVariadicPlaceholder = $node->args[0];
        if (! $argOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $firstArgValue = $argOrVariadicPlaceholder->value;

        $resolvedTemplateFilePath = $this->pathResolver->resolveExistingFilePath($firstArgValue, $scope);
        if ($resolvedTemplateFilePath === null) {
            return [];
        }

        $missingVariableNames = $this->missingLatteTemplateRenderVariableResolver->resolveFromTemplateAndMethodCall(
            $node,
            $resolvedTemplateFilePath,
            $scope
        );

        if ($missingVariableNames === []) {
            return [];
        }

        $unusedPassedVariablesString = implode('", "', $missingVariableNames);
        $errorMessage = sprintf(self::ERROR_MESSAGE, $unusedPassedVariablesString);
        return [$errorMessage];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Control;

final class SomeControl extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/some_file.latte');
    }
}

// some_file.latte
{$usedValue}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Control;

final class SomeControl extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/some_file.latte', [
            'usedValue' => 'value'
        ]);
    }
}

// some_file.latte
{$usedValue}
CODE_SAMPLE
            ),
        ]);
    }
}
