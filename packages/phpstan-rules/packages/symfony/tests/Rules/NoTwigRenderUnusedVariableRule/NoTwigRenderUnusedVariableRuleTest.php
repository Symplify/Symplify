<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\Tests\Rules\NoTwigRenderUnusedVariableRule;

use Iterator;
use PHPStan\Rules\Rule;
use Symplify\PHPStanExtensions\Testing\AbstractServiceAwareRuleTestCase;
use Symplify\PHPStanRules\Symfony\Rules\NoTwigRenderUnusedVariableRule;

/**
 * @extends AbstractServiceAwareRuleTestCase<NoTwigRenderUnusedVariableRule>
 */
final class NoTwigRenderUnusedVariableRuleTest extends AbstractServiceAwareRuleTestCase
{
    /**
     * @dataProvider provideData()
     * @param array<string|int> $expectedErrorMessagesWithLines
     */
    public function testRule(string $filePath, array $expectedErrorMessagesWithLines): void
    {
        $this->analyse([$filePath], $expectedErrorMessagesWithLines);
    }

    public function provideData(): Iterator
    {
        yield [__DIR__ . '/Fixture/RenderWithUnusedVariable.php', [
            [sprintf(NoTwigRenderUnusedVariableRule::ERROR_MESSAGE, 'unused_variable'), 14],
        ]];

        yield [__DIR__ . '/Fixture/RenderBareTwigWithUnusedVariable.php', [
            [sprintf(NoTwigRenderUnusedVariableRule::ERROR_MESSAGE, 'unused_variable'), 13],
        ]];

        yield [__DIR__ . '/Fixture/SkipUsedVariable.php', []];
        yield [__DIR__ . '/Fixture/SkipForeachUsedVariable.php', []];

        yield [__DIR__ . '/Fixture/RenderTwoTemplates.php', [
            [sprintf(NoTwigRenderUnusedVariableRule::ERROR_MESSAGE, 'unused_variable'), 16],
        ]];
    }

    protected function getRule(): Rule
    {
        return $this->getRuleFromConfig(
            NoTwigRenderUnusedVariableRule::class,
            __DIR__ . '/config/configured_rule.neon'
        );
    }
}
