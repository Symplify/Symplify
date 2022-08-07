<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Symplify\Astral\NodeAnalyzer\NetteTypeAnalyzer;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Nette\Rules\RequireTemplateInNetteControlRule\RequireTemplateInNetteControlRuleTest
 */
final class RequireTemplateInNetteControlRule implements Rule, DocumentedRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Set control template explicitly in $this->template->setFile(...) or $this->template->render(...)';

    public function __construct(
        private NodeFinder $nodeFinder,
        private NetteTypeAnalyzer $netteTypeAnalyzer
    ) {
    }

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->netteTypeAnalyzer->isInsideControl($scope)) {
            return [];
        }

        $classMethodName = $node->name->toString();
        if ($classMethodName !== 'render' && ! str_starts_with($classMethodName, 'render')) {
            return [];
        }

        if ($this->hasTemplateSet($node)) {
            return [];
        }

        return [self::ERROR_MESSAGE];
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
        $this->template->render('some_file.latte');
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    private function hasTemplateSet(ClassMethod $classMethod): bool
    {
        /** @var MethodCall[] $methodCalls */
        $methodCalls = $this->nodeFinder->findInstanceOf($classMethod, MethodCall::class);

        foreach ($methodCalls as $methodCall) {
            if (! $methodCall->name instanceof Identifier) {
                continue;
            }

            $methodCallName = $methodCall->name->toString();
            if (! in_array($methodCallName, ['setFile', 'render'], true)) {
                continue;
            }

            if (! isset($methodCall->args[0])) {
                continue;
            }

            return true;
        }

        return false;
    }
}
