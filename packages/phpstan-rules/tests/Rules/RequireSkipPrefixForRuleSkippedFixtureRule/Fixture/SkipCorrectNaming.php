<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\RequireSkipPrefixForRuleSkippedFixtureRule\Fixture;

use PHPStan\Rules\DeadCode\UnusedPrivateConstantRule;
use PHPStan\Testing\RuleTestCase;

final class SkipCorrectNaming extends RuleTestCase
{
    public function provideData(): \Iterator
    {
        yield [__DIR__ . '/Fixture/SkipCorrectNaming.php', []];
    }

    protected function getRule(): \PHPStan\Rules\Rule
    {
        return new UnusedPrivateConstantRule();
    }
}
