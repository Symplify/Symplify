<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\Explicit\NoRelativeFilePathRule\Fixture;

final class RelativeFilePath
{
    public function run()
    {
        return 'some_relative_path.txt';
    }
}
