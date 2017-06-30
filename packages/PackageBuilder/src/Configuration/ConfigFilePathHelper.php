<?php declare(strict_types=1);

namespace Symplify\PackageBuilder\Configuration;

use Symfony\Component\Console\Input\InputInterface;
use Symplify\PackageBuilder\Exception\Configuration\FileNotFoundException;

final class ConfigFilePathHelper
{
    /**
     * @var string
     */
    private const CONFIG_OPTION_NAME = '--config';

    /**
     * @var string[]
     */
    private static $configFilePaths = [];

    public static function detectFromInput(string $name, InputInterface $input): void
    {
        if ($input->hasParameterOption(self::CONFIG_OPTION_NAME)) {
            $relativeFilePath = $input->getParameterOption(self::CONFIG_OPTION_NAME);
            $filePath = getcwd() . '/' . $relativeFilePath;

            if (!file_exists($filePath)) {
                throw new FileNotFoundException(sprintf(
                    'File "%s" not found in "%s".',
                    $filePath,
                    $relativeFilePath
                ));
            }

            self::$configFilePaths[$name] = $filePath;
        }
    }

    public static function provide(string $name): ?string
    {
        if (isset(self::$configFilePaths[$name])) {
            return self::$configFilePaths[$name];
        }

        $rootConfigPath = getcwd() . '/' . $name . '.neon';
        if (file_exists($rootConfigPath)) {
            return self::$configFilePaths[$name] = $rootConfigPath;
        }

        return null;
    }

    public static function set(string $name, string $configFilePath): void
    {
        self::$configFilePaths[$name] = $configFilePath;
    }
}
