<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Nette\FileSystem;

use PHPStan\Analyser\Scope;
use Symplify\PHPStanRules\LattePHPStanPrinter\ValueObject\PhpFileContentsWithLineMap;
use Symplify\SmartFileSystem\SmartFileSystem;

final class PHPLatteFileDumper
{
    public function __construct(
        private SmartFileSystem $smartFileSystem
    ) {
    }

    public function dump(PhpFileContentsWithLineMap $phpFileContentsWithLineMap, Scope $scope): string
    {
        $tmpFilePath = sys_get_temp_dir() . '/' . md5($scope->getFile()) . '-latte-compiled.php';
        $phpFileContents = $phpFileContentsWithLineMap->getPhpFileContents();

        $this->smartFileSystem->dumpFile($tmpFilePath, $phpFileContents);

        return $tmpFilePath;
    }
}
