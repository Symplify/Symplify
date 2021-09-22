<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\Dibi;

use Nette\Utils\Strings;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Symplify\Astral\NodeValue\NodeValueResolver;

/**
 * E.g. from "INSERT %s %v" Will extact: s, v
 */
final class QueryMasksResolver
{
    /**
     * @see https://regex101.com/r/tXL1UV/1
     * @var string
     */
    private const MASK_REGEX = '#(?<mask>%\w+)\b#';

    public function __construct(
        private NodeValueResolver $nodeValueResolver,
    ) {
    }

    /**
     * @return string[]
     */
    public function resolveQueryMasks(MethodCall $methodCall, Scope $scope): array
    {
        $firstArg = $methodCall->args[0];
        if (! $firstArg instanceof Arg) {
            return [];
        }

        $queryString = $this->nodeValueResolver->resolve($firstArg->value, $scope->getFile());
        if (! is_string($queryString)) {
            return [];
        }

        return $this->resolveMasksFromString($queryString);
    }

    public function resolveSingleQueryMask(Expr|null $expr, Scope $scope): ?string
    {
        if ($expr === null) {
            return null;
        }

        $dimValue = $this->nodeValueResolver->resolve($expr, $scope->getFile());
        if (! is_string($dimValue)) {
            return null;
        }

        $masks = $this->resolveMasksFromString($dimValue);
        if (count($masks) !== 1) {
            return null;
        }

        return $masks[0];
    }

    /**
     * @return string[]
     */
    public function resolveMasksFromString(string $queryString): array
    {
        // compare only if string contains masks
        if (! str_contains($queryString, '%')) {
            return [];
        }

        $maskMatches = Strings::matchAll($queryString, self::MASK_REGEX);

        $masks = [];
        foreach ($maskMatches as $maskMatch) {
            $masks[] = $maskMatch['mask'];
        }

        return $masks;
    }
}
