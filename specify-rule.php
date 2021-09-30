<?php

use Nette\Utils\Strings;
use Symplify\SmartFileSystem\SmartFileSystem;

require __DIR__ . '/vendor/autoload.php';

$finder = new Symfony\Component\Finder\Finder();
$fileInfos = $finder->in(__DIR__ . '/packages/phpstan-rules/tests')
    ->files()
    ->name('*.neon')
    ->getIterator();

$smartFileSystem = new SmartFileSystem();

foreach ($fileInfos as $fileInfo) {
    $configFileContent = $smartFileSystem->readFile($fileInfo->getRealPath());
    // 1. is generic config?
    if (! str_contains($configFileContent, 'symplify-rules.neon')) {
        continue;
    }

    // 2. detect rule class name from the test
    $baseRuleDirectory = dirname($fileInfo->getPath());
    $specificRuleDirectory = Strings::after($baseRuleDirectory, 'tests/');

    $specificRuleDirectory = str_replace('/', '\\', $specificRuleDirectory);

    $ruleClass = 'Symplify\\PHPStanRules\\' . $specificRuleDirectory;
    if (! class_exists($ruleClass)) {
        continue;
    }

    // 3. remove symplify-rules line
    $configFileContentLines = explode(PHP_EOL, $configFileContent);
    foreach ($configFileContentLines as $key => $configFileContentLine) {
        if (! str_contains($configFileContentLine, 'symplify-rules.neon')) {
            continue;
        }

        unset($configFileContentLines[$key]);
    }

    $configFileContent = implode(PHP_EOL, $configFileContentLines);

    // 4. add services section with rule registration :)

    $ruleServicesTemplate = <<<'CODE_SAMPLE'
services:
    -
        class: %s
        tags: [phpstan.rules.rule]
CODE_SAMPLE;

    $ruleServicesContent = sprintf($ruleServicesTemplate, $ruleClass);

    $newConfigContent = $configFileContent . PHP_EOL . $ruleServicesContent . PHP_EOL;

    // 5. dump content
    $smartFileSystem->dumpFile($fileInfo->getRealPath(), $newConfigContent);
}
