<?php

declare(strict_types=1);

namespace Symplify\PhpConfigPrinter\CaseConverter;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use Symplify\PhpConfigPrinter\Contract\CaseConverterInterface;
use Symplify\PhpConfigPrinter\NodeFactory\ArgsNodeFactory;
use Symplify\PhpConfigPrinter\NodeFactory\CommonNodeFactory;
use Symplify\PhpConfigPrinter\Provider\CurrentFilePathProvider;
use Symplify\PhpConfigPrinter\ValueObject\MethodName;
use Symplify\PhpConfigPrinter\ValueObject\VariableName;
use Symplify\PhpConfigPrinter\ValueObject\YamlKey;

/**
 * Handles this part:
 *
 * parameters: <---
 */
final class ParameterCaseConverter implements CaseConverterInterface
{
    public function __construct(
        private ArgsNodeFactory $argsNodeFactory,
        private CurrentFilePathProvider $currentFilePathProvider,
        private CommonNodeFactory $commonNodeFactory
    ) {
    }

    public function match(string $rootKey, $key, $values): bool
    {
        return $rootKey === YamlKey::PARAMETERS;
    }

    public function convertToMethodCall($key, $values): Expression
    {
        if (is_string($values)) {
            $values = $this->prefixWithDirConstantIfExistingPath($values);
        }

        if (is_array($values)) {
            foreach ($values as $subKey => $subValue) {
                if (! is_string($subValue)) {
                    continue;
                }

                $values[$subKey] = $this->prefixWithDirConstantIfExistingPath($subValue);
            }
        }

        $args = $this->argsNodeFactory->createFromValues([$key, $values]);

        $parametersVariable = new Variable(VariableName::PARAMETERS);
        $methodCall = new MethodCall($parametersVariable, MethodName::SET, $args);

        return new Expression($methodCall);
    }

    private function prefixWithDirConstantIfExistingPath(string $value): string | Expr
    {
        $filePath = $this->currentFilePathProvider->getFilePath();
        if ($filePath === null) {
            return $value;
        }

        $configDirectory = dirname($filePath);

        $possibleConfigPath = $configDirectory . '/' . $value;
        if (is_file($possibleConfigPath)) {
            return $this->commonNodeFactory->createAbsoluteDirExpr($value);
        }

        if (is_dir($possibleConfigPath)) {
            return $this->commonNodeFactory->createAbsoluteDirExpr($value);
        }

        return $value;
    }
}
