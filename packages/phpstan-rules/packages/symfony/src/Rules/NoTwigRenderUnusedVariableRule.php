<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\PHPStanRules\Symfony\NodeAnalyzer\SymfonyRenderWithParametersMatcher;
use Symplify\PHPStanRules\Symfony\NodeAnalyzer\Template\UnusedTwigTemplateVariableAnalyzer;
use Symplify\PHPStanRules\Symfony\ValueObject\RenderTemplateWithParameters;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Symfony\Tests\Rules\NoTwigRenderUnusedVariableRule\NoTwigRenderUnusedVariableRuleTest
 */
final class NoTwigRenderUnusedVariableRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Passed "%s" variable is not used in the template';

    public function __construct(
        private UnusedTwigTemplateVariableAnalyzer $unusedTwigTemplateVariableAnalyzer,
        private SymfonyRenderWithParametersMatcher $symfonyRenderWithParametersMatcher
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
        $renderTemplateWithParameters = $this->symfonyRenderWithParametersMatcher->matchTwigRender($node, $scope);
        if (! $renderTemplateWithParameters instanceof RenderTemplateWithParameters) {
            return [];
        }

        $unusedVariableNames = $this->unusedTwigTemplateVariableAnalyzer->resolveMethodCallAndTemplate(
            $node,
            $renderTemplateWithParameters->getTemplateFilePaths(),
            $scope
        );

        if ($unusedVariableNames === []) {
            return [];
        }

        $errorMessages = [];
        foreach ($unusedVariableNames as $unusedVariableName) {
            $errorMessages[] = sprintf(self::ERROR_MESSAGE, $unusedVariableName);
        }

        return $errorMessages;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
$environment = new Twig\Environment();
$environment->render(__DIR__ . '/some_file.twig', [
    'used_variable' => 'value'
]);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$environment = new Twig\Environment();
$environment->render(__DIR__ . '/some_file.twig', [
    'unused_variable' => 'value'
]);
CODE_SAMPLE
            ),
        ]);
    }
}
