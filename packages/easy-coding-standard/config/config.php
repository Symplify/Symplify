<?php

declare(strict_types=1);

use PHP_CodeSniffer\Fixer;
use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\UnusedFunctionParameterSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Classes\PropertyDeclarationSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Methods\MethodDeclarationSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\PHP\CommentedOutCodeSniff;
use PhpCsFixer\Differ\DifferInterface;
use PhpCsFixer\Differ\UnifiedDiffer;
use PhpCsFixer\WhitespacesFixerConfig;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symplify\EasyCodingStandard\Application\Version\StaticVersionResolver;
use Symplify\EasyCodingStandard\Caching\Cache;
use Symplify\EasyCodingStandard\Caching\CacheFactory;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\Console\Style\EasyCodingStandardStyle;
use Symplify\EasyCodingStandard\Console\Style\EasyCodingStandardStyleFactory;
use Symplify\EasyCodingStandard\FixerRunner\Application\FixerFileProcessor;
use Symplify\EasyCodingStandard\FixerRunner\WhitespacesFixerConfigFactory;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\PackageBuilder\Console\Style\SymfonyStyleFactory;
use Symplify\PackageBuilder\Yaml\ParametersMerger;
use Symplify\SmartFileSystem\FileSystemFilter;
use Symplify\SmartFileSystem\FileSystemGuard;
use Symplify\SmartFileSystem\Finder\FinderSanitizer;
use Symplify\SmartFileSystem\Finder\SmartFinder;
use Symplify\SmartFileSystem\SmartFileSystem;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->indentation(Option::INDENTATION_SPACES);
    $ecsConfig->lineEnding(PHP_EOL);

    $cacheDirectory = sys_get_temp_dir() . '/changed_files_detector%env(TEST_SUFFIX)%';
    if (StaticVersionResolver::PACKAGE_VERSION !== '@package_version@') {
        $cacheDirectory .= '_' . StaticVersionResolver::PACKAGE_VERSION;
    }

    $ecsConfig->cacheDirectory($cacheDirectory);

    $cacheNamespace = str_replace(DIRECTORY_SEPARATOR, '_', getcwd());
    $ecsConfig->cacheNamespace($cacheNamespace);

    // parallel
    $ecsConfig->parallel();

    // ECS only knows about errors, these are the classes allowed to promote warnings to errors
    $ecsConfig->reportSniffClassWarnings([
        '\PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff',
        PropertyDeclarationSniff::class,
        MethodDeclarationSniff::class,
        CommentedOutCodeSniff::class,
        UnusedFunctionParameterSniff::class,
    ]);

    $ecsConfig->paths([]);
    $ecsConfig->fileExtensions(['php']);

    $parameters = $ecsConfig->parameters();
    $parameters->set('env(TEST_SUFFIX)', '');

    $services = $ecsConfig->services();
    $services->defaults()
        ->public()
        ->autowire();

    $services->load('Symplify\EasyCodingStandard\\', __DIR__ . '/../src')
        ->exclude([
            // only for "bin/ecs" file, where container does not exist yet
            __DIR__ . '/../src/Config/ECSConfig.php',
            __DIR__ . '/../src/DependencyInjection',
            __DIR__ . '/../src/Kernel',
            __DIR__ . '/../src/Exception',
            __DIR__ . '/../src/ValueObject',
            // for 3rd party tests
            __DIR__ . '/../src/Testing',
        ]);

    $services->load('Symplify\EasyCodingStandard\\', __DIR__ . '/../packages')
        ->exclude([__DIR__ . '/../packages/*/ValueObject/*']);

    $services->set(Cache::class)
        ->factory([service(CacheFactory::class), 'create']);

    $services->set(Terminal::class);

    $services->set(FileSystemGuard::class);
    $services->set(FinderSanitizer::class);
    $services->set(SmartFileSystem::class);
    $services->set(SmartFinder::class);
    $services->set(FileSystemFilter::class);

    $services->set(SymfonyStyleFactory::class);
    $services->set(SymfonyStyle::class)
        ->factory([service(SymfonyStyleFactory::class), 'create']);

    $services->set(ParametersMerger::class);

    $services->set(EasyCodingStandardStyle::class)
        ->factory([service(EasyCodingStandardStyleFactory::class), 'create']);

    $services->set(WhitespacesFixerConfig::class)
        ->factory([service(WhitespacesFixerConfigFactory::class), 'create']);

    // code sniffer
    $services->set(Fixer::class);

    // fixer
    $services->set(UnifiedDiffer::class);
    $services->alias(DifferInterface::class, UnifiedDiffer::class);
    $services->set(FixerFileProcessor::class);
};
