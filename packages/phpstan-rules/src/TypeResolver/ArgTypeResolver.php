<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\TypeResolver;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;

final class ArgTypeResolver
{
    /**
     * @return Type[]
     */
    public function resolveArgTypesWithoutFirst(FuncCall|MethodCall|StaticCall $call, Scope $scope): array
    {
        $args = $call->args;
        unset($args[0]);

        $argTypes = [];
        foreach ($args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            $argTypes[] = $scope->getType($arg->value);
        }

        return $argTypes;
    }
}
