<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodingStyle\Enum\PreferenceSelfThis;
use Rector\CodingStyle\Rector\ClassMethod\UnSpreadOperatorRector;
use Rector\CodingStyle\Rector\MethodCall\PreferThisOrSelfMethodCallRector;
use Rector\CodingStyle\Rector\String_\SymplifyQuoteEscapeRector;
use Rector\Core\Configuration\Option;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\FunctionLike\ParamTypeDeclarationRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\SymfonyPhpConfig\ValueObjectInliner;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(SetList::CODE_QUALITY);
    $containerConfigurator->import(SetList::CODE_QUALITY_STRICT);

    // uncomment after phpstan is using php-parser 4.13, to avoid false positives
    // $containerConfigurator->import(SetList::DEAD_CODE);

    $containerConfigurator->import(SetList::CODING_STYLE);
    $containerConfigurator->import(SetList::PHP_54);
    $containerConfigurator->import(SetList::PHP_55);
    $containerConfigurator->import(SetList::PHP_56);
    $containerConfigurator->import(SetList::PHP_70);
    $containerConfigurator->import(SetList::PHP_71);
    $containerConfigurator->import(SetList::PHP_72);
    $containerConfigurator->import(SetList::PHP_73);
    $containerConfigurator->import(SetList::PHP_74);
    $containerConfigurator->import(SetList::PHP_80);
    $containerConfigurator->import(SetList::TYPE_DECLARATION);
    $containerConfigurator->import(SetList::TYPE_DECLARATION_STRICT);
    $containerConfigurator->import(SetList::NAMING);
    $containerConfigurator->import(SetList::PRIVATIZATION);
    $containerConfigurator->import(SetList::EARLY_RETURN);
    $containerConfigurator->import(PHPUnitSetList::PHPUNIT_CODE_QUALITY);

    $services = $containerConfigurator->services();

    // PHP 8+
    $services->set(ClosureToArrowFunctionRector::class);
    $services->set(ClassPropertyAssignToConstructorPromotionRector::class);
    $services->set(ChangeSwitchToMatchRector::class);
    $services->set(ClassPropertyAssignToConstructorPromotionRector::class);
    $services->set(TypedPropertyRector::class);

    $services->set(StringClassNameToClassConstantRector::class)
        ->call('configure', [[
            StringClassNameToClassConstantRector::CLASSES_TO_SKIP => [
                'Error',
                'Exception',
                'Dibi\Connection',
                'Doctrine\ORM\EntityManagerInterface',
                'Doctrine\ORM\EntityManager',
                'Nette\Application\UI\Template',
                'Nette\DI\Attributes\Inject',
                'Nette\Bridges\ApplicationLatte\Template',
                'Nette\Bridges\ApplicationLatte\DefaultTemplate',
                'Nette\Bridges\ApplicationLatte\UIMacros',
                'Nette\Bridges\FormsLatte\FormMacros',
                'Nette\Security\User',
                'Nette\Application\UI\Control',
                'Nette\Application\UI\Presenter',
            ],
        ]]);

    $services->set(PreferThisOrSelfMethodCallRector::class)
        ->call('configure', [[
            PreferThisOrSelfMethodCallRector::TYPE_TO_PREFERENCE => [
                TestCase::class => ValueObjectInliner::inline(PreferenceSelfThis::PREFER_THIS()),
            ],
        ]]);

    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::AUTO_IMPORT_NAMES, true);
    $parameters->set(Option::AUTOLOAD_PATHS, [__DIR__ . '/tests/bootstrap.php', __DIR__ . '/ecs.php']);

    $parameters->set(Option::PATHS, [__DIR__ . '/packages']);

    $parameters->set(Option::SKIP, [
        '*/scoper.php',
        '*/vendor/*',
        '*/init/*',
        '*/Source/*',
        '*/Fixture/*',
        '*/Fixture*/*',
        '*/ChangedFilesDetectorSource/*',
        __DIR__ . '/packages/monorepo-builder/templates',
        __DIR__ . '/packages/easy-coding-standard/build/build-preload.php',

        // many false positives related to file class autoload
        __DIR__ . '/packages/easy-coding-standard/bin/ecs.php',

        # tests
        __DIR__ . '/packages/vendor-patches/tests/Finder/VendorFilesFinderSource/Vendor/some/package/src/PackageClass.php',

        // many false postivies
        RenameForeachValueVariableToMatchExprVariableRector::class,
        StringClassNameToClassConstantRector::class => [
            // for prefixed version skip
            __DIR__ . '/packages/php-config-printer/src/PhpParser/NodeFactory/ConfiguratorClosureNodeFactory.php',
        ],

        // on purpose Latte macro magic
        SymplifyQuoteEscapeRector::class => [
            __DIR__ . '/packages/phpstan-rules/packages/latte-phpstan-printer/src/Latte/Macros/LatteMacroFaker.php',
        ],

        // buggy on array access object
        UnusedForeachValueToArrayKeysRector::class => [
            __DIR__ . '/packages/coding-standard/src/Fixer/Annotation/DoctrineAnnotationNestedBracketsFixer.php',
        ],

        // buggy with parent interface contract
        ParamTypeDeclarationRector::class => [__DIR__ . '/packages/skipper/src/SkipVoter/*SkipVoter.php'],
        UnSpreadOperatorRector::class => [__DIR__ . '/packages/git-wrapper'],

        CallableThisArrayToAnonymousFunctionRector::class => [
            // not a callable, accidental array
            __DIR__ . '/packages/phpstan-rules/src/Rules/',
        ],

        // protected param rename to create miss-matching param names with parent class method
        // @see https://github.com/symplify/symplify/pull/3429 - paths must be relative, so child process in specific directory skips it
        RenameParamToMatchTypeRector::class => [
            'src/Php/Type/NativeFunctionDynamicFunctionReturnTypeExtension.php',
            'src/Printer/PhpParserPhpConfigPrinter.php',
            'src/DependencyInjection/Loader/IdAwareXmlFileLoader.php',
        ],
    ]);
};
