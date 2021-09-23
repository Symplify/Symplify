<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\FileAnalyser;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Registry;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use Symplify\PHPStanRules\ErrorSkipper;
use Symplify\PHPStanRules\Nette\NodeAnalyzer\TemplateRenderAnalyzer;
use Symplify\PHPStanRules\Nette\TemplateFileVarTypeDocBlocksDecorator;
use Symplify\PHPStanRules\NodeAnalyzer\PathResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\PHPStanRules\Rules\ForbiddenFuncCallRule;
use Symplify\PHPStanRules\Rules\NoDynamicNameRule;
use Symplify\PHPStanRules\Templates\TemplateErrorsFactory;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\SmartFileSystem\SmartFileSystem;
use Throwable;

/**
 * @see \Symplify\PHPStanRules\Nette\Tests\Rules\LatteCompleteCheckRule\LatteCompleteCheckRuleTest
 *
 * @inspired at https://github.com/efabrica-team/phpstan-latte/blob/main/src/Rule/ControlLatteRule.php#L56
 */
final class LatteCompleteCheckRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Complete analysis of PHP code generated from Latte template';

    /**
     * @todo use regex and phpstan-like filtering
     * @var string[]
     */
    private const ERROR_IGNORES = [
        // nette
        '#DummyTemplateClass#',
        '#Method Nette\\\\Application\\\\UI\\\\Renderable::redrawControl\(\) invoked with#',
        '#Ternary operator condition is always (.*?)#',
        '#Access to an undefined property Latte\\\\Runtime\\\\FilterExecutor::#',
        '#Anonymous function should have native return typehint "void"#',
        // impossible to resolve with conditions in PHP
        '#might not be defined#',
        '#has an unused variable#',
    ];

    /**
     * @var array<class-string<DocumentedRuleInterface>>
     */
    private const EXCLUDED_RULES = [ForbiddenFuncCallRule::class, NoDynamicNameRule::class];

    private Registry $registry;

    /**
     * @param Rule[] $rules
     */
    public function __construct(
        array $rules,
        private FileAnalyser $fileAnalyser,
        private TemplateRenderAnalyzer $templateRenderAnalyzer,
        private PathResolver $pathResolver,
        private SmartFileSystem $smartFileSystem,
        private TemplateFileVarTypeDocBlocksDecorator $templateFileVarTypeDocBlocksDecorator,
        private ErrorSkipper $errorSkipper,
        private TemplateErrorsFactory $templateErrorsFactory,
    ) {
        // limit rule here, as template class can contain lot of allowed Latte magic
        // get missing method + missing property etc. rule
        $activeRules = $this->filterActiveRules($rules);

        $this->registry = new Registry($activeRules);
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
     * @return RuleError[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if (! $this->templateRenderAnalyzer->isNetteTemplateRenderMethodCall($node, $scope)) {
            return [];
        }

        // must be template path + variables
        if (count($node->args) !== 2) {
            return [];
        }

        $firstArgValue = $node->args[0]->value;

        // @todo use similar approach to Twig rule
        $resolvedTemplateFilePaths = $this->pathResolver->resolveExistingFilePaths($firstArgValue, $scope);
        if ($resolvedTemplateFilePaths === []) {
            return [];
        }

        $secondArgValue = $node->args[1]->value;
        if (! $secondArgValue instanceof Array_) {
            return [];
        }

        $errors = [];
        foreach ($resolvedTemplateFilePaths as $resolvedTemplateFilePath) {
            $currentErrors = $this->processTemplateFilePath($resolvedTemplateFilePath, $secondArgValue, $scope);
            $errors = array_merge($errors, $currentErrors);
        }

        return $errors;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Control;

class SomeClass extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/some_control.latte', [
            'some_type' => new SomeType
        ]);
    }
}

// some_control.latte
{$some_type->missingMethod()}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Control;

class SomeClass extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/some_control.latte', [
            'some_type' => new SomeType
        ]);
    }
}


// some_control.latte
{$some_type->existingMethod()}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @param Rule[] $rules
     * @return Rule[]
     */
    private function filterActiveRules(array $rules): array
    {
        $activeRules = [];

        foreach ($rules as $rule) {
            foreach (self::EXCLUDED_RULES as $excludedRule) {
                if (is_a($rule, $excludedRule, true)) {
                    continue 2;
                }
            }

            $activeRules[] = $rule;
        }
        return $activeRules;
    }

    /**
     * @return RuleError[]
     */
    private function processTemplateFilePath(string $templateFilePath, Array_ $array, Scope $scope): array
    {
        try {
            $phpFileContentsWithLineMap = $this->templateFileVarTypeDocBlocksDecorator->decorate(
                $templateFilePath,
                $array,
                $scope
            );
        } catch (Throwable) {
            // missing include/layout template or something else went wrong → we cannot analyse template here
            return [];
        }

        $tmpFilePath = sys_get_temp_dir() . '/' . md5($scope->getFile()) . '-latte-compiled.php';
        $phpFileContents = $phpFileContentsWithLineMap->getPhpFileContents();

        $this->smartFileSystem->dumpFile($tmpFilePath, $phpFileContents);

        // to include generated class
        $fileAnalyserResult = $this->fileAnalyser->analyseFile($tmpFilePath, [], $this->registry, null);

        // remove errors related to just created class, that cannot be autoloaded
        $errors = $this->errorSkipper->skipErrors($fileAnalyserResult->getErrors(), self::ERROR_IGNORES);

        return $this->templateErrorsFactory->createErrors($errors, $templateFilePath, $phpFileContentsWithLineMap);
    }
}
