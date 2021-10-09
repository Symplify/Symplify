<?php

declare(strict_types=1);

namespace Symplify\TemplatePHPStanCompiler\NodeAnalyzer;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;

final class MethodCallArrayResolver
{
    public function __construct(
        private ParametersArrayAnalyzer $parametersArrayAnalyzer
    ) {
    }

    /**
     * @return string[]
     */
    public function resolveArrayKeysOnPosition(MethodCall $methodCall, Scope $scope, int $position): array
    {
        if (! isset($methodCall->args[$position])) {
            return [];
        }

        $argOrVariadicPlaceholder = $methodCall->args[$position];
        if (! $argOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $secondArgValue = $argOrVariadicPlaceholder->value;
        if (! $secondArgValue instanceof Array_) {
            return [];
        }

        return $this->parametersArrayAnalyzer->resolveStringKeys($secondArgValue, $scope);
    }
}
