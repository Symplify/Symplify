<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\PreferredMethodCallOverIdenticalCompareRule;

use Symplify\PHPStanRules\Tests\Rules\PreferredMethodCallOverIdenticalCompareRule\Fixture\AbstractRector;
use Iterator;
use PHPStan\Rules\Rule;
use Symplify\PHPStanExtensions\Testing\AbstractServiceAwareRuleTestCase;
use Symplify\PHPStanRules\Rules\PreferredMethodCallOverIdenticalCompareRule;
use Symplify\SmartFileSystem\SmartFileSystem;

final class PreferredMethodCallOverIdenticalCompareRuleTest extends AbstractServiceAwareRuleTestCase
{
    /**
     * @dataProvider provideData()
     * @param mixed[] $expectedErrorMessagesWithLines
     */
    public function testRule(string $filePath, array $expectedErrorMessagesWithLines): void
    {
        $this->analyse([$filePath], $expectedErrorMessagesWithLines);
    }

    public function provideData(): Iterator
    {
        $errorMessage = sprintf(
            PreferredMethodCallOverIdenticalCompareRule::ERROR_MESSAGE,
            AbstractRector::class,
            'isName',
            AbstractRector::class,
            'getName'
        );

        yield [__DIR__ . '/Fixture/SkipNotMethodCall.php', []];
        yield [__DIR__ . '/Fixture/ARector.php', [[$errorMessage, 13]]];
        yield [__DIR__ . '/Fixture/DependencyInjectionRector.php', [[$errorMessage, 20]]];
    }

    protected function getRule(): Rule
    {
        return $this->getRuleFromConfig(
            PreferredMethodCallOverIdenticalCompareRule::class,
            __DIR__ . '/config/configured_rule.neon'
        );
    }
}
