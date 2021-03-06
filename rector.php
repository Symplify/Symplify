<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Rector\CodingStyle\Rector\ClassMethod\UnSpreadOperatorRector;
use Rector\CodingStyle\Rector\MethodCall\PreferThisOrSelfMethodCallRector;
use Rector\CodingStyle\ValueObject\PreferenceSelfThis;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\ProjectType;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeLocalOnlyMethodRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(StringClassNameToClassConstantRector::class)
        ->call('configure', [[
            StringClassNameToClassConstantRector::CLASSES_TO_SKIP => [
                'Error',
                'Exception',
                'Doctrine\ORM\EntityManagerInterface',
                'Doctrine\ORM\EntityManager',
                'Nette\Application\UI\Template',
                'Nette\Bridges\ApplicationLatte\Template',
                'Nette\Bridges\ApplicationLatte\DefaultTemplate',
            ],
        ]]);

    $services->set(PreferThisOrSelfMethodCallRector::class)
        ->call('configure', [[
            PreferThisOrSelfMethodCallRector::TYPE_TO_PREFERENCE => [
                TestCase::class => PreferenceSelfThis::PREFER_THIS,
            ],
        ]]);

    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::AUTO_IMPORT_NAMES, true);
    $parameters->set(Option::AUTOLOAD_PATHS, [__DIR__ . '/tests/bootstrap.php', __DIR__ . '/ecs.php']);

    $parameters->set(Option::SETS, [
        SetList::CODE_QUALITY,
        SetList::CODE_QUALITY_STRICT,
        SetList::DEAD_CODE,
        SetList::CODING_STYLE,
        SetList::PHP_54,
        SetList::PHP_55,
        SetList::PHP_56,
        SetList::PHP_70,
        SetList::PHP_71,
        SetList::PHP_72,
        SetList::PHP_73,
<<<<<<< HEAD
<<<<<<< HEAD
        //        SetList::TYPE_DECLARATION,
        //        SetList::TYPE_DECLARATION_STRICT,
=======
        SetList::TYPE_DECLARATION,
        SetList::TYPE_DECLARATION_STRICT,
>>>>>>> b8ac1a5d0... add more types
=======
        //        SetList::TYPE_DECLARATION,
        //        SetList::TYPE_DECLARATION_STRICT,
>>>>>>> 63a1a53b6... composer: allow Rector dev
        SetList::PHPUNIT_CODE_QUALITY,
        //        SetList::NAMING,
        SetList::PRIVATIZATION,
        // enable later
        // SetList::DEAD_CLASSES,
        SetList::EARLY_RETURN,
    ]);

    $parameters->set(Option::PATHS, [__DIR__ . '/packages']);
    $parameters->set(Option::ENABLE_CACHE, true);

    $parameters->set(Option::PROJECT_TYPE, ProjectType::OPEN_SOURCE);

    $parameters->set(Option::SKIP, [
        '*/scoper.inc.php',
        '*/vendor/*',
        '*/init/*',
        '*/Source/*',
        '*/Fixture/*',
        '*/ChangedFilesDetectorSource/*',
        __DIR__ . '/packages/monorepo-builder/packages/init/templates',

        // many false positives related to file class autoload
        __DIR__ . '/packages/easy-coding-standard/bin/ecs.php',

        # tests
        __DIR__ . '/packages/vendor-patches/tests/Finder/VendorFilesFinderSource/Vendor/some/package/src/PackageClass.php',

        // many false postivies
<<<<<<< HEAD
        \Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector::class,
=======
        RenameForeachValueVariableToMatchExprVariableRector::class,
>>>>>>> 63a1a53b6... composer: allow Rector dev

        PrivatizeLocalOnlyMethodRector::class => [
            // @api + used in test
            __DIR__ . '/packages/symfony-static-dumper/tests/test_project/src/HttpKernel/TestSymfonyStaticDumperKernel.php',
            __DIR__ . '/packages/phpstan-rules/tests/Rules/ForbiddenArrayWithStringKeysRule/FixturePhp80/SkipAttributeArrayKey.php',
        ],

        __DIR__ . '/packages/sniffer-fixer-to-ecs-converter/stubs/Sniff.php',

        UnSpreadOperatorRector::class => [__DIR__ . '/packages/git-wrapper'],

        // false positive, max actually returns mixed, not int, see https://www.php.net/manual/en/function.max.php
        RecastingRemovalRector::class => [__DIR__ . '/packages/changelog-linker/src/Analyzer/IdsAnalyzer.php'],
    ]);
};
