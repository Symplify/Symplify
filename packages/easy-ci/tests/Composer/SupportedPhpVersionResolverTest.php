<?php

declare(strict_types=1);

namespace Symplify\EasyCI\Tests\Composer;

use Iterator;
use Symplify\EasyCI\Composer\SupportedPhpVersionResolver;
use Symplify\EasyCI\HttpKernel\EasyCIKernel;
use Symplify\PackageBuilder\Testing\AbstractKernelTestCase;

final class SupportedPhpVersionResolverTest extends AbstractKernelTestCase
{
    /**
     * @var SupportedPhpVersionResolver
     */
    private $supportedPhpVersionResolver;

    protected function setUp(): void
    {
        $this->bootKernel(EasyCIKernel::class);
        $this->supportedPhpVersionResolver = $this->getService(SupportedPhpVersionResolver::class);
    }

    /**
     * @dataProvider provideData()
     * @param string[] $expectedPhpVersions
     */
    public function test(string $constraints, array $expectedPhpVersions): void
    {
        $supportedVersions = $this->supportedPhpVersionResolver->resolveFromConstraints($constraints);
        $this->assertSame($expectedPhpVersions, $supportedVersions);
    }

    public function provideData(): Iterator
    {
        yield ['>= 8.0', ['8.0']];
    }
}
