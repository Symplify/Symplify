<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\Nette\NodeAnalyzer\TemplateRenderAnalyzer;
use Symplify\PHPStanRules\Nette\NodeAnalyzer\UnusedNetteTemplateRenderVariableResolver;
use Symplify\PHPStanRules\NodeAnalyzer\PathResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Nette\Tests\Rules\NoNetteRenderUnusedVariableRule\NoNetteRenderUnusedVariableRuleTest
 */
final class NoNetteRenderUnusedVariableRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Extra variables "%s" are passed to the template but never used there';

    public function __construct(
        private TemplateRenderAnalyzer $templateRenderAnalyzer,
        private PathResolver $pathResolver,
        private UnusedNetteTemplateRenderVariableResolver $unusedNetteTemplateRenderVariableResolver,
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

        if (count($node->args) < 2) {
            return [];
        }

        $firstArgValue = $node->args[0]->value;

        $templateFilePaths = $this->pathResolver->resolveExistingFilePaths($firstArgValue, $scope);
        if ($templateFilePaths === []) {
            return [];
        }

        $unusedVariableNamesByTemplateFilePath = [];

        foreach ($templateFilePaths as $templateFilePath) {
            $unusedVariableNamesByTemplateFilePath[$templateFilePath] = $this->unusedNetteTemplateRenderVariableResolver->resolveMethodCallAndTemplate(
                $node,
                $templateFilePath,
                $scope
            );
        }

        if ($unusedVariableNamesByTemplateFilePath === []) {
            return [];
        }

        $errors = [];

        foreach ($unusedVariableNamesByTemplateFilePath as $templateFilePath => $unusedVariableNames) {
            if ($unusedVariableNames === []) {
                continue;
            }

            $unusedPassedVariablesString = implode('", "', $unusedVariableNames);
            $errors[] = sprintf(self::ERROR_MESSAGE, $unusedPassedVariablesString);
        }

        return $errors;
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
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Control;

final class SomeControl extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/some_file.latte', [
            'never_used_in_template' => 'value'
        ]);
    }
}
CODE_SAMPLE
            ),
        ]);
    }
}
