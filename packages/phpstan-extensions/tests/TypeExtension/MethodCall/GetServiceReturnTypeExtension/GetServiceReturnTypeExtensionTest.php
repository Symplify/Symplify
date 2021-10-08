<?php

declare(strict_types=1);

namespace Symplify\PHPStanExtensions\Tests\TypeExtension\MethodCall\GetServiceReturnTypeExtension;

use PHPStan\Testing\TypeInferenceTestCase;

final class GetServiceReturnTypeExtensionTest extends TypeInferenceTestCase
{
    public function dataAsserts(): iterable
    {
        yield from $this->gatherAssertTypes(__DIR__ . '/data/fixture.php');
    }

    /**
     * @dataProvider dataAsserts()
     *
     * @param mixed ...$args
     */
    public function testAsserts(string $assertType, string $file, ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /**
     * @return string[]
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/config.neon'];
    }
}
