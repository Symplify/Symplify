<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\NoNullableArrayPropertyRule\Fixture;

final class NullableArrayProperty
{
    private ?array $value = [];

    public function run()
    {
        $value;
    }
}

