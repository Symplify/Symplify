<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\PreventDoubleSetParameterRule;

use Iterator;
use PHPStan\Rules\Rule;
use Symplify\PHPStanExtensions\Testing\AbstractServiceAwareRuleTestCase;
use Symplify\PHPStanRules\Rules\PreventDoubleSetParameterRule;

final class PreventDoubleSetParameterRuleTest extends AbstractServiceAwareRuleTestCase
{
    /**
     * @dataProvider provideData()
     */
    public function testRule(string $filePath, array $expectedErrorMessagesWithLines): void
    {
        $this->analyse([$filePath], $expectedErrorMessagesWithLines);
    }

    public function provideData(): Iterator
    {
        yield [__DIR__ . '/Fixture/ConfigParameter.php', []];
    }

    protected function getRule(): Rule
    {
        return $this->getRuleFromConfig(
            PreventDoubleSetParameterRule::class,
            __DIR__ . '/../../../config/symplify-rules.neon'
        );
    }
}
