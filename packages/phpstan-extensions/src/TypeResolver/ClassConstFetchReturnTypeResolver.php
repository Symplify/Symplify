<?php

declare(strict_types=1);

namespace Symplify\PHPStanExtensions\TypeResolver;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Symplify\PHPStanRules\Naming\SimpleNameResolver;

final class ClassConstFetchReturnTypeResolver
{
    /**
     * @var SimpleNameResolver
     */
    private $simpleNameResolver;

    public function __construct(SimpleNameResolver $simpleNameResolver)
    {
        $this->simpleNameResolver = $simpleNameResolver;
    }

    public function resolve(MethodReflection $methodReflection, MethodCall $methodCall): Type
    {
        $returnType = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();
        if (! isset($methodCall->args[0])) {
            return $returnType;
        }

        $className = $this->simpleNameResolver->getName($methodCall->args[0]->value);
        if ($className !== null) {
            return new ObjectType($className);
        }

        return $returnType;
    }
}
