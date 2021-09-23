<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\Rules;

use PhpParser\Node;
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

        $firstArgValue = $node->args[0]->value;

        $templateFilePaths = $this->pathResolver->resolveExistingFilePaths($firstArgValue, $scope);
        if ($templateFilePaths === []) {
            return [];
        }

        $missingVariableNames = [];
        foreach ($templateFilePaths as $templateFilePath) {
            $currentMissingVariableNames = $this->missingLatteTemplateRenderVariableResolver->resolveFromTemplateAndMethodCall(
                $node,
                $templateFilePath,
                $scope
            );

            $missingVariableNames = array_merge($missingVariableNames, $currentMissingVariableNames);
        }

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
