<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\CheckParentChildMethodParameterTypeCompatibleRule\Fixture;

class InsideOtherMethod
{
    private const A = 'a';

    public function otherMethod()
    {
        $a = self::A;
    }
}
