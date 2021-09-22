<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\PHPStan;

use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use Symplify\PHPStanRules\Rules\ForbiddenFuncCallRule;
use Symplify\PHPStanRules\Rules\NoDynamicNameRule;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;

/**
 * Remove unwanted rules and errors from template analysis
 */
final class LattePHPStanRulesRegistryAndIgnoredErrorsFilter
{
    /**
     * @var string[]
     */
    private const IGNORED_ERROR_MESSAGES = [
        'DummyTemplateClass',
        'Method Nette\Application\UI\Renderable::redrawControl() invoked with',
        'Ternary operator condition is always true',
        'Access to an undefined property Latte\Runtime\FilterExecutor::',
        'Anonymous function should have native return typehint "void"',
        // impossible to resolve with conditions in PHP
        '#might not be defined#',
        '#has an unused variable#',
    ];

    /**
     * @var array<class-string<DocumentedRuleInterface>>
     */
    private const EXCLUDED_RULES = [ForbiddenFuncCallRule::class, NoDynamicNameRule::class];

    /**
     * @param Rule[] $rules
     * @return Rule[]
     */
    public function filterActiveRules(array $rules): array
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
     * @param Error[] $errors
     * @return Error[]
     */
    public function filterErrors(array $errors): array
    {
        return array_filter($errors, fn (Error $error): bool => $this->shouldKeepError($error));
    }

    private function shouldKeepError(Error $error): bool
    {
        foreach (self::IGNORED_ERROR_MESSAGES as $ignoredErrorMessage) {
            if (str_contains($error->getMessage(), $ignoredErrorMessage)) {
                return false;
            }
        }

        return true;
    }
}
