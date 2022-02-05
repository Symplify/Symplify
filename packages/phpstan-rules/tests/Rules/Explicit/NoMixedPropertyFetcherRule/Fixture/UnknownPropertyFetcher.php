<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\Explicit\NoMixedPropertyFetcherRule\Fixture;

final class UnknownPropertyFetcher
{
    public function run($unknownType)
    {
        $unknownType->name;
    }
}
