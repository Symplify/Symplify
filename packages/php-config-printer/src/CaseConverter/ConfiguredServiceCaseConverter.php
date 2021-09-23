<?php

declare(strict_types=1);

namespace Symplify\PhpConfigPrinter\CaseConverter;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use Symplify\PhpConfigPrinter\Contract\CaseConverterInterface;
use Symplify\PhpConfigPrinter\NodeFactory\ArgsNodeFactory;
use Symplify\PhpConfigPrinter\NodeFactory\Service\ServiceOptionNodeFactory;
use Symplify\PhpConfigPrinter\ValueObject\MethodName;
use Symplify\PhpConfigPrinter\ValueObject\VariableName;
use Symplify\PhpConfigPrinter\ValueObject\YamlKey;

final class ConfiguredServiceCaseConverter implements CaseConverterInterface
{
    public function __construct(
        private ArgsNodeFactory $argsNodeFactory,
        private ServiceOptionNodeFactory $serviceOptionNodeFactory
    ) {
    }

    public function convertToMethodCall($key, $values): Expression
    {
        $valuesForArgs = [$key];

        if (isset($values[YamlKey::CLASS_KEY])) {
            $valuesForArgs[] = $values[YamlKey::CLASS_KEY];
        }

        $args = $this->argsNodeFactory->createFromValues($valuesForArgs);
        $methodCall = new MethodCall(new Variable(VariableName::SERVICES), MethodName::SET, $args);

        $decoratedMethodCall = $this->serviceOptionNodeFactory->convertServiceOptionsToNodes($values, $methodCall);
        return new Expression($decoratedMethodCall);
    }

    public function match(string $rootKey, $key, $values): bool
    {
        if ($rootKey !== YamlKey::SERVICES) {
            return false;
        }

        if ($key === YamlKey::_DEFAULTS) {
            return false;
        }

        if ($key === YamlKey::_INSTANCEOF) {
            return false;
        }

        if (isset($values[YamlKey::RESOURCE])) {
            return false;
        }

        // handled by @see \Symplify\PhpConfigPrinter\CaseConverter\CaseConverter\AliasCaseConverter
        if ($this->isAlias($values)) {
            return false;
        }

        if ($values === null) {
            return false;
        }

        return $values !== [];
    }

    private function isAlias($values): bool
    {
        if (isset($values[YamlKey::ALIAS])) {
            return true;
        }

        if (! is_string($values)) {
            return false;
        }

        return \str_starts_with($values, '@');
    }
}
