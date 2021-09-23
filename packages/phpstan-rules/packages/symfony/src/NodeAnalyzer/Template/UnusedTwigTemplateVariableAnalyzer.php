<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\NodeAnalyzer\Template;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\NodeAnalyzer\MethodCallArrayResolver;

final class UnusedTwigTemplateVariableAnalyzer
{
    public function __construct(
        private TwigVariableNamesResolver $twigVariableNamesResolver,
        private MethodCallArrayResolver $methodCallArrayResolver
    ) {
    }

    /**
     * @return string[]
     */
    public function resolveMethodCallAndTemplate(
        MethodCall $methodCall,
        array $templateFilePaths,
        Scope $scope
    ): array {
        $templateUsedVariableNames = $this->twigVariableNamesResolver->resolveFromFiles($templateFilePaths);
        $passedVariableNames = $this->methodCallArrayResolver->resolveArrayKeysOnPosition($methodCall, $scope, 1);

        return array_diff($passedVariableNames, $templateUsedVariableNames);
    }
}
