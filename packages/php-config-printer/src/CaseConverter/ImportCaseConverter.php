<?php

declare(strict_types=1);

namespace Symplify\PhpConfigPrinter\CaseConverter;

use Nette\Utils\Strings;
use PhpParser\BuilderHelpers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Symplify\PhpConfigPrinter\Contract\CaseConverterInterface;
use Symplify\PhpConfigPrinter\Exception\NotImplementedYetException;
use Symplify\PhpConfigPrinter\NodeFactory\CommonNodeFactory;
use Symplify\PhpConfigPrinter\Sorter\YamlArgumentSorter;
use Symplify\PhpConfigPrinter\ValueObject\VariableName;
use Symplify\PhpConfigPrinter\ValueObject\YamlKey;

/**
 * Handles this part:
 *
 * imports: <---
 */
final class ImportCaseConverter implements CaseConverterInterface
{
    /**
     * @see https://regex101.com/r/hOTdIE/1
     * @var string
     */
    private const INPUT_SUFFIX_REGEX = '#\.(yml|yaml|xml)$#';

    public function __construct(
        private YamlArgumentSorter $yamlArgumentSorter,
        private CommonNodeFactory $commonNodeFactory
    ) {
    }

    public function match(string $rootKey, $key, $values): bool
    {
        return $rootKey === YamlKey::IMPORTS;
    }

    /**
     * @param mixed|mixed[] $values
     */
    public function convertToMethodCall($key, $values): Expression
    {
        if (is_array($values)) {
            $arguments = $this->yamlArgumentSorter->sortArgumentsByKeyIfExists($values, [
                YamlKey::RESOURCE => '',
                'type' => null,
                YamlKey::IGNORE_ERRORS => false,
            ]);
            return $this->createImportMethodCall($arguments);
        }

        if (is_string($values)) {
            return $this->createImportMethodCall([$values]);
        }

        throw new NotImplementedYetException();
    }

    /**
     * @param mixed[] $arguments
     */
    private function createImportMethodCall(array $arguments): Expression
    {
        $containerConfiguratorVariable = new Variable(VariableName::CONTAINER_CONFIGURATOR);

        $args = $this->createArgs($arguments);
        $methodCall = new MethodCall($containerConfiguratorVariable, 'import', $args);

        return new Expression($methodCall);
    }

    /**
     * @param array<int|string, mixed> $arguments
     * @return Arg[]
     */
    private function createArgs(array $arguments): array
    {
        $args = [];
        foreach ($arguments as $name => $value) {
            if (is_string($name) && $this->shouldSkipDefaultValue($name, $value, $arguments)) {
                continue;
            }

            $expr = $this->resolveExpr($value);
            $args[] = new Arg($expr);
        }

        return $args;
    }

    private function shouldSkipDefaultValue(string $name, $value, array $arguments): bool
    {
        // skip default value for "ignore_errors"
        if ($name === YamlKey::IGNORE_ERRORS && $value === false) {
            return true;
        }

        // check if default value for "type"
        if ($name !== 'type') {
            return false;
        }

        if ($value !== null) {
            return false;
        }

        // follow by default value for "ignore_errors"
        if (! isset($arguments[YamlKey::IGNORE_ERRORS])) {
            return false;
        }

        return $arguments[YamlKey::IGNORE_ERRORS] === false;
    }

    /**
     * @return mixed|string
     */
    private function replaceImportedFileSuffix($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        return Strings::replace($value, self::INPUT_SUFFIX_REGEX, '.php');
    }

    private function resolveExpr($value): Expr
    {
        if (is_bool($value)) {
            return BuilderHelpers::normalizeValue($value);
        }

        if (in_array($value, ['annotations', 'directory', 'glob'], true)) {
            return BuilderHelpers::normalizeValue($value);
        }

        if ($value === 'not_found') {
            return new String_('not_found');
        }

        if (is_string($value) && \str_contains($value, '::')) {
            [$className, $constantName] = explode('::', $value);
            return new ClassConstFetch(new FullyQualified($className), $constantName);
        }

        $value = $this->replaceImportedFileSuffix($value);
        return $this->commonNodeFactory->createAbsoluteDirExpr($value);
    }
}
