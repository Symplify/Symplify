<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\NodeAnalyzer;

use PhpParser\Node\Arg;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Analyser\Scope;
use Symplify\Astral\NodeValue\NodeValueResolver;

final class ScalarValueResolver
{
    public function __construct(
        private NodeValueResolver $nodeValueResolver
    ) {
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $args
     * @return mixed[]
     */
    public function resolveValuesCountFromArgs(array $args, Scope $scope): array
    {
        $resolveValues = $this->resolvedValues($args, $scope->getFile());

        // filter out false/true values
        $resolvedValuesWithoutBool = \array_filter($resolveValues, fn ($value) => ! $this->shouldSkipValue($value));
        if ($resolvedValuesWithoutBool === []) {
            return [];
        }

        return $this->countValues($resolvedValuesWithoutBool);
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $args
     * @return mixed[]
     */
    private function resolvedValues(array $args, string $filePath): array
    {
        $passedValues = [];
        foreach ($args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            $resolvedValue = $this->nodeValueResolver->resolve($arg->value, $filePath);

            // skip simple values
            if ($resolvedValue === '') {
                continue;
            }

            // unwrap single array item
            if (\is_array($resolvedValue) && \count($resolvedValue) === 1) {
                $resolvedValue = \array_pop($resolvedValue);
            }

            $passedValues[] = $resolvedValue;
        }

        return $passedValues;
    }

    /**
     * @param mixed[] $values
     * @return mixed[]
     */
    private function countValues(array $values): array
    {
        if ($values === []) {
            return [];
        }

        // the array_count_values ignores "null", so we have to translate it to string here
        $values = array_filter($values, fn (mixed $value) => $this->isFilterableValue($value));

        return \array_count_values($values);
    }

    /**
     * Makes values ready for array_count_values(), it accepts only numeric or strings; no objects nor arrays
     */
    private function isFilterableValue(mixed $value): bool
    {
        if (is_numeric($value)) {
            return true;
        }

        return is_string($value);
    }

    private function shouldSkipValue(mixed $value): bool
    {
        // value could not be resolved
        if ($value === null) {
            return true;
        }

        if (is_array($value)) {
            return true;
        }

        // simple values, probably boolean markers or type constants
        if (\in_array($value, [0, 1], true)) {
            return true;
        }

        return \is_bool($value);
    }
}
