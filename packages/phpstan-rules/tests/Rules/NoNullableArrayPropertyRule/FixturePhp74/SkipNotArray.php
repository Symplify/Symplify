<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\NoNullableArrayPropertyRule\FixturePhp74;

final class SkipNotArray
{
    private ?object $value;

    public function run()
    {
        $values;
    }
}

