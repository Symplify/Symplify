<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\NodeAnalyzer\Template;

use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\NodeAnalyzer\ArrayAnalyzer;
use Symplify\PHPStanRules\Symfony\ValueObject\RenderTemplateWithParameters;

final class MissingTwigTemplateRenderVariableResolver
{
    public function __construct(
        private TwigVariableNamesResolver $twigVariableNamesResolver,
        private ArrayAnalyzer $arrayAnalyzer
    ) {
    }

    /**
     * @return string[]
     */
    public function resolveFromTemplateAndMethodCall(
        RenderTemplateWithParameters $renderTemplateWithParameters,
        Scope $scope
    ): array {
        $templateUsedVariableNames = $this->twigVariableNamesResolver->resolveFromFiles(
            $renderTemplateWithParameters->getTemplateFilePaths()
        );

        $availableVariableNames = $this->arrayAnalyzer->resolveStringKeys(
            $renderTemplateWithParameters->getParametersArray(),
            $scope
        );

        // default variables
        $availableVariableNames[] = 'app';

        $missingVariableNames = array_diff($templateUsedVariableNames, $availableVariableNames);

        return array_unique($missingVariableNames);
    }
}
