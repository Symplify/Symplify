<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\Tests\Rules\ForbiddenNestedForeachWithEmptyStatementRule\Fixture;

foreach ($errors as $fileErrors) {
    // empty
    foreach ($fileErrors as $fileError) {

    }
}