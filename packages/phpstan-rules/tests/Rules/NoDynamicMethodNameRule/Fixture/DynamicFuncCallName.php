<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\NoDynamicMethodNameRule\Fixture;

final class DynamicFuncCallName
{
    public function run($value)
    {
        $value();
    }
}
