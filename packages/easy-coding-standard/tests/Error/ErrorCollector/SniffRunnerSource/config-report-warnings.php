<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\Tests\Error\ErrorCollector\SniffRunnerSource\WarnOnPrintFakeSniff;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(WarnOnPrintFakeSniff::class);
};
