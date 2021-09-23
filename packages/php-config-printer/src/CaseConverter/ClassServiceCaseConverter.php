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

final class ClassServiceCaseConverter implements CaseConverterInterface
{
    public function __construct(
        private ArgsNodeFactory $argsNodeFactory,
        private ServiceOptionNodeFactory $serviceOptionNodeFactory
    ) {
    }

    public function convertToMethodCall($key, $values): Expression
    {
        $args = $this->argsNodeFactory->createFromValues([$key, $values[YamlKey::CLASS_KEY]]);
        $methodCall = new MethodCall(new Variable(VariableName::SERVICES), MethodName::SET, $args);

        unset($values[YamlKey::CLASS_KEY]);

        $decoratedMethodCall = $this->serviceOptionNodeFactory->convertServiceOptionsToNodes($values, $methodCall);
        return new Expression($decoratedMethodCall);
    }

    public function match(string $rootKey, $key, $values): bool
    {
        if ($rootKey !== YamlKey::SERVICES) {
            return false;
        }

        if (is_array($values) && count($values) !== 1) {
            return false;
        }

        if (! isset($values[YamlKey::CLASS_KEY])) {
            return false;
        }

        return ! isset($values[YamlKey::ALIAS]);
    }
}
