<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\NodeAnalyzer;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PHPStanRules\NodeAnalyzer\PathResolver;
use Symplify\PHPStanRules\Symfony\ValueObject\RenderTemplateWithParameters;
use Twig\Environment;

final class SymfonyRenderWithParametersMatcher
{
    /**
     * @var string
     */
    private const RENDER = 'render';

    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private PathResolver $pathResolver,
    ) {
    }

    public function matchSymfonyRender(MethodCall $methodCall, Scope $scope): RenderTemplateWithParameters|null
    {
        if (! $this->simpleNameResolver->isNames($methodCall->name, [self::RENDER, 'renderView'])) {
            return null;
        }

        $methodCallReturnType = $scope->getType($methodCall);
        if (! $methodCallReturnType instanceof ObjectType) {
            return null;
        }

        if (! $methodCallReturnType->isInstanceOf(Response::class)->yes()) {
            return null;
        }

        return $this->matchRenderTempalteWithParmatersOnArgs($methodCall, $scope);
    }

    public function matchTwigRender(MethodCall $methodCall, Scope $scope): RenderTemplateWithParameters|null
    {
        $callerType = $scope->getType($methodCall->var);
        if ($callerType instanceof ThisType) {
            $callerType = new ObjectType($callerType->getClassName());
        }

        if (! $callerType instanceof ObjectType) {
            return null;
        }

        if (! $this->isTwigCallerType($callerType, $methodCall)) {
            return null;
        }

        return $this->matchRenderTempalteWithParmatersOnArgs($methodCall, $scope);
    }

    private function resolveParameterArray(MethodCall $methodCall): Array_
    {
        if (! isset($methodCall->args[1])) {
            return new Array_();
        }

        $secondArgValue = $methodCall->args[1]->value;
        if (! $secondArgValue instanceof Array_) {
            return new Array_();
        }

        return $secondArgValue;
    }

    private function isTwigCallerType(ObjectType $objectType, MethodCall $methodCall): bool
    {
        if ($objectType->isInstanceOf(Environment::class)->yes()) {
            return $this->simpleNameResolver->isName($methodCall->name, self::RENDER);
        }

        if ($objectType->isInstanceOf(AbstractController::class)->yes()) {
            return $this->simpleNameResolver->isNames($methodCall->name, [self::RENDER, 'renderView']);
        }

        return false;
    }

    private function matchRenderTempalteWithParmatersOnArgs(
        MethodCall $methodCall,
        Scope $scope
    ): ?RenderTemplateWithParameters {
        $firstArgValue = $methodCall->args[0]->value;

        $resolvedTemplateFilePaths = $this->pathResolver->resolveExistingFilePaths($firstArgValue, $scope);
        if ($resolvedTemplateFilePaths === []) {
            return null;
        }

        // we need array parameters
        $parametersArray = $this->resolveParameterArray($methodCall);

        return new RenderTemplateWithParameters($resolvedTemplateFilePaths, $parametersArray);
    }
}
