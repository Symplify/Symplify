<?php

declare(strict_types=1);

use PhpParser\ConstExprEvaluator;
use PhpParser\NodeFinder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\PackageBuilder\Php\TypeChecker;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('Symplify\Astral\\', __DIR__ . '/../src')
        ->exclude([
            __DIR__ . '/../src/HttpKernel',
            __DIR__ . '/../src/StaticFactory',
            __DIR__ . '/../src/ValueObject',
            __DIR__ . '/../src/NodeVisitor',
        ]);

    $services->set(ConstExprEvaluator::class);
    $services->set(TypeChecker::class);
    $services->set(NodeFinder::class);
};
