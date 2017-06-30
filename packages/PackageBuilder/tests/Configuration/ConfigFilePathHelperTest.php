<?php declare(strict_types=1);

namespace Symplify\PackageBuilder\Configuration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symplify\PackageBuilder\Exception\Configuration\FileNotFoundException;

final class ConfigFilePathHelperTest extends TestCase
{
    public function testDetectFromInputAndProvide(): void
    {
        ConfigFilePathHelper::detectFromInput('another-name', new ArrayInput([
            '--config' => 'phpstan.neon'
        ]));

        $this->assertSame(getcwd() . '/phpstan.neon', ConfigFilePathHelper::provide('another-name'));
    }

    public function testMissingFileInInput(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'File "%s" not found in "%s"',
            getcwd() . '/someFile.neon',
            'someFile.neon'
        ));

        ConfigFilePathHelper::detectFromInput('name', new ArrayInput([
            '--config' => 'someFile.neon'
        ]));
    }
}