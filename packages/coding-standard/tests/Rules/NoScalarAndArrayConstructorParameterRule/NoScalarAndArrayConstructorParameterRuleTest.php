<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\Tests\Rules\NoScalarAndArrayConstructorParameterRule;

use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Symplify\CodingStandard\PHPStan\Types\ScalarTypeAnalyser;
use Symplify\CodingStandard\PHPStan\VariableAsParamAnalyser;
use Symplify\CodingStandard\Rules\NoScalarAndArrayConstructorParameterRule;
use Symplify\PackageBuilder\Reflection\PrivatesAccessor;

final class NoScalarAndArrayConstructorParameterRuleTest extends RuleTestCase
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
        yield [__DIR__ . '/Fixture/ValueObject/SkipValueObject.php', []];
        yield [__DIR__ . '/Fixture/ValueObject/Deep/SomeConstruct.php', []];
        yield [__DIR__ . '/Fixture/ValueObject/Deep/VeryDeep/SomeConstruct.php', []];

        yield [__DIR__ . '/Fixture/SkipWithoutConstruct.php', []];
        yield [__DIR__ . '/Fixture/SomeWithConstructParameterNonScalar.php', []];
        yield [__DIR__ . '/Fixture/SomeWithConstructParameterNoType.php', []];
        yield [__DIR__ . '/Fixture/SomeWithConstructParameterNullableNonScalar.php', []];
        yield [__DIR__ . '/Fixture/SkipNonConstruct.php', []];
        yield [__DIR__ . '/Fixture/SkipAutowireArrayTypes.php', []];

        yield [
            __DIR__ . '/Fixture/StringScalarType.php',
            [[NoScalarAndArrayConstructorParameterRule::ERROR_MESSAGE, 16]],
        ];

        yield [
            __DIR__ . '/Fixture/BoolScalarType.php',
            [[NoScalarAndArrayConstructorParameterRule::ERROR_MESSAGE, 16]],
        ];

        yield [__DIR__ . '/Fixture/StringArray.php', [[NoScalarAndArrayConstructorParameterRule::ERROR_MESSAGE, 19]]];

        yield [__DIR__ . '/Fixture/SkipDummyArray.php', []];

        yield [__DIR__ . '/Fixture/IntScalarType.php', [[NoScalarAndArrayConstructorParameterRule::ERROR_MESSAGE, 16]]];

        yield [__DIR__ . '/Fixture/FloatScalarType.php', [
            [NoScalarAndArrayConstructorParameterRule::ERROR_MESSAGE, 19],
        ]];

        yield [__DIR__ . '/Fixture/SomeWithConstructParameterNullableScalar.php', [
            [NoScalarAndArrayConstructorParameterRule::ERROR_MESSAGE, 16],
        ]];
    }

    protected function getRule(): Rule
    {
        return new NoScalarAndArrayConstructorParameterRule(
            new VariableAsParamAnalyser(new PrivatesAccessor()),
            new ScalarTypeAnalyser()
        );
    }
}
