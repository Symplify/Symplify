<?php

declare(strict_types=1);

namespace Symplify\EasyCI\Neon\Application;

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Nette\Utils\Arrays;
use Symplify\EasyCI\Contract\Application\FileProcessorInterface;
use Symplify\EasyCI\Contract\ValueObject\FileErrorInterface;
use Symplify\EasyCI\ValueObject\FileError;
use Symplify\SmartFileSystem\SmartFileInfo;
use Symplify\SmartFileSystem\SmartFileSystem;

/**
 * @see \Symplify\EasyCI\Tests\Neon\Application\NeonFilesProcessor\NeonFilesProcessorTest
 */
final class NeonFilesProcessor implements FileProcessorInterface
{
    /**
     * @var string
     */
    private const SERVICES_KEY = 'services';

    /**
     * @var string
     */
    private const SETUP_KEY = 'setup';

    public function __construct(
        private SmartFileSystem $smartFileSystem
    ) {
    }

    /**
     * @param SmartFileInfo[] $fileInfos
     * @return FileErrorInterface[]
     */
    public function processFileInfos(array $fileInfos): array
    {
        $fileErrors = [];

        foreach ($fileInfos as $fileInfo) {
            $neon = $this->readNeonFile($fileInfo);

            // 1. we only take care about services
            $servicesNeon = $neon[self::SERVICES_KEY] ?? null;
            if ($servicesNeon === null) {
                continue;
            }

            $currentFileErrors = $this->processServicesSection($servicesNeon, $fileInfo);
            $fileErrors = array_merge($fileErrors, $currentFileErrors);
        }

        return $fileErrors;
    }

    /**
     * @return mixed[]
     */
    private function readNeonFile(SmartFileInfo $fileInfo): array
    {
        $fileContent = $this->smartFileSystem->readFile($fileInfo->getRealPath());
        return Neon::decode($fileContent);
    }

    private function createErrorMessageFromNeonEntity(Entity $neonEntity): string
    {
        $neonEntityContent = Neon::encode($neonEntity);

        return sprintf(
            'Complex entity found "%s"%s   Change it to explicit syntax with named keys, that is easier to read.',
            $neonEntityContent,
            PHP_EOL,
        );
    }

    /**
     * @param mixed[] $servicesNeon
     * @return FileErrorInterface[]
     */
    private function processServicesSection(array $servicesNeon, SmartFileInfo $fileInfo): array
    {
        $fileErrors = [];

        foreach ($servicesNeon as $singleService) {
            if ($singleService instanceof Entity) {
                $errorMessage = $this->createErrorMessageFromNeonEntity($singleService);
                $fileErrors[] = new FileError($errorMessage, $fileInfo);
                continue;
            }

            // 1. the "setup" has allowed entities
            $singleService = $this->removeSetupKey($singleService);

            // 2. detect complex neon entities
            $flatNeon = Arrays::flatten($singleService, true);
            foreach ($flatNeon as $itemNeon) {
                if (! $itemNeon instanceof Entity) {
                    continue;
                }

                $errorMessage = $this->createErrorMessageFromNeonEntity($itemNeon);
                $fileErrors[] = new FileError($errorMessage, $fileInfo);
            }
        }

        return $fileErrors;
    }

    /**
     * @param array<int|string, mixed> $singleService
     * @return array<int|string, mixed>
     */
    private function removeSetupKey(array $singleService): array
    {
        if (isset($singleService[self::SETUP_KEY])) {
            unset($singleService[self::SETUP_KEY]);
        }

        return $singleService;
    }
}
