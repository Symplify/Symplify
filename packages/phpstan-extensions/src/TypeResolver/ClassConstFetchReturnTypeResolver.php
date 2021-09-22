<?php

declare(strict_types=1);

namespace Symplify\PHPStanExtensions\TypeResolver;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\Astral\StaticFactory\SimpleNameResolverStaticFactory;

final class ClassConstFetchReturnTypeResolver
{
    private SimpleNameResolver $simpleNameResolver;

    public function __construct()
    {
        // intentionally manual here, to prevent double service registration caused by nette/di
        $this->simpleNameResolver = SimpleNameResolverStaticFactory::create();
    }

    public function resolve(MethodReflection $methodReflection, MethodCall $methodCall): Type
    {
        $returnType = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();

        if (! isset($methodCall->args[0])) {
            return $returnType;
        }

        $firstArgOrVariadciPlaceholder = $methodCall->args[0];
        if (! $firstArgOrVariadciPlaceholder instanceof Arg) {
            return new MixedType();
        }

        $firstValue = $firstArgOrVariadciPlaceholder->value;
        if (! $firstValue instanceof ClassConstFetch) {
            return new MixedType();
        }

        $className = $this->simpleNameResolver->getName($firstValue);
        if ($className !== null) {
            return new ObjectType($className);
        }

        return $returnType;
    }
}
