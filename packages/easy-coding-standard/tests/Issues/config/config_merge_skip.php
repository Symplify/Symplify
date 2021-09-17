<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    // common has skip option
    $containerConfigurator->import(SetList::COMMON);

    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::SKIP, [
        ReturnAssignmentFixer::class,
    ]);
};
