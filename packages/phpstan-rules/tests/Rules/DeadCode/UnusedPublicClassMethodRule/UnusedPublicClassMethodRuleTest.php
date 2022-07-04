<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\DeadCode\UnusedPublicClassMethodRule;

use Iterator;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Symplify\PHPStanRules\Collector\ClassMethod\MethodCallCollector;
use Symplify\PHPStanRules\Collector\ClassMethod\PublicClassMethodCollector;
use Symplify\PHPStanRules\DeadCode\UnusedPublicClassMethodRule;

/**
 * @extends RuleTestCase<UnusedPublicClassMethodRule>
 */
final class UnusedPublicClassMethodRuleTest extends RuleTestCase
{
    /**
     * @dataProvider provideData()
     * @param string[] $filePaths
     * @param mixed[] $expectedErrorMessagesWithLines
     */
    public function testRule(array $filePaths, array $expectedErrorMessagesWithLines): void
    {
        $this->analyse($filePaths, $expectedErrorMessagesWithLines);
    }

    public function provideData(): Iterator
    {
        $errorMessage = sprintf(UnusedPublicClassMethodRule::ERROR_MESSAGE, 'runHere');
        yield [[__DIR__ . '/Fixture/LocallyUsedPublicMethod.php'], [[$errorMessage, 14]]];

        yield [[__DIR__ . '/Fixture/SkipInterfaceMethod.php'], []];
        yield [[__DIR__ . '/Fixture/SkipPrivateClassMethod.php'], []];
        yield [[__DIR__ . '/Fixture/SkipUsedPublicMethod.php', __DIR__ . '/Source/ClassMethodCaller.php'], []];
    }

    /**
     * @return string[]
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/configured_rule.neon'];
    }

    /**
     * @return array<Collector>
     */
    protected function getCollectors(): array
    {
        return [new MethodCallCollector(), new PublicClassMethodCollector()];
    }

    protected function getRule(): Rule
    {
        $container = self::getContainer();
        return $container->getByType(UnusedPublicClassMethodRule::class);
    }
}
