<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\FileAnalyser;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Registry;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symplify\PHPStanRules\LattePHPStanPrinter\ValueObject\PhpFileContentsWithLineMap;
use Symplify\PHPStanRules\Nette\FileSystem\PHPLatteFileDumper;
use Symplify\PHPStanRules\Nette\NodeAnalyzer\TemplateRenderAnalyzer;
use Symplify\PHPStanRules\Nette\PHPStan\LattePHPStanRulesRegistryAndIgnoredErrorsFilter;
use Symplify\PHPStanRules\Nette\TemplateFileVarTypeDocBlocksDecorator;
use Symplify\PHPStanRules\NodeAnalyzer\PathResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
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

    private Registry $registry;

    /**
     * @param Rule[] $rules
     */
    public function __construct(
        array $rules,
        private FileAnalyser $fileAnalyser,
        private TemplateRenderAnalyzer $templateRenderAnalyzer,
        private PathResolver $pathResolver,
        private TemplateFileVarTypeDocBlocksDecorator $templateFileVarTypeDocBlocksDecorator,
        private PHPLatteFileDumper $phpLatteFileDumper,
        private LattePHPStanRulesRegistryAndIgnoredErrorsFilter $lattePHPStanRulesRegistryAndIgnoredErrorsFilter
    ) {
        // limit rule here, as template class can contain lot of allowed Latte magic
        // get missing method + missing property etc. rule
        $activeRules = $this->lattePHPStanRulesRegistryAndIgnoredErrorsFilter->filterActiveRules($rules);

        // HACK for prevent circular reference...
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

        $firstArg = $node->args[0];
        if (! $firstArg instanceof Arg) {
            return [];
        }

        $firstArgValue = $firstArg->value;

        $resolvedTemplateFilePath = $this->pathResolver->resolveExistingFilePath($firstArgValue, $scope);
        if ($resolvedTemplateFilePath === null) {
            return [];
        }

        $secondArgOrVariadicPlaceholder = $node->args[1];
        if (! $secondArgOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $secondArgValue = $secondArgOrVariadicPlaceholder->value;
        if (! $secondArgValue instanceof Array_) {
            return [];
        }

        try {
            $phpFileContentsWithLineMap = $this->templateFileVarTypeDocBlocksDecorator->decorate(
                $resolvedTemplateFilePath,
                $secondArgValue,
                $scope
            );
        } catch (Throwable) {
            // missing include/layout template or something else went wrong → we cannot analyse template here
            return [];
        }

        $dumperPhpFilePath = $this->phpLatteFileDumper->dump($phpFileContentsWithLineMap, $scope);

        // to include generated class
        $fileAnalyserResult = $this->fileAnalyser->analyseFile($dumperPhpFilePath, [], $this->registry, null);

        // remove errors related to just created class, that cannot be autoloaded
        $errors = $this->lattePHPStanRulesRegistryAndIgnoredErrorsFilter->filterErrors(
            $fileAnalyserResult->getErrors()
        );

        return $this->createErrors($errors, $resolvedTemplateFilePath, $phpFileContentsWithLineMap);
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
     * @return RuleError[]
     */
    private function createErrors(
        array $errors,
        string $resolvedTemplateFilePath,
        PhpFileContentsWithLineMap $phpFileContentsWithLineMap
    ): array {
        $ruleErrors = [];

        $phpToTemplateLines = $phpFileContentsWithLineMap->getPhpToTemplateLines();

        foreach ($errors as $error) {
            // correct error PHP line number to Latte line number
            $errorLine = (int) $error->getLine();
            $errorLine = $phpToTemplateLines[$errorLine] ?? $errorLine;

            $ruleErrors[] = RuleErrorBuilder::message($error->getMessage())
                ->file($resolvedTemplateFilePath)
                ->line($errorLine)
                ->build();
        }

        return $ruleErrors;
    }
}
