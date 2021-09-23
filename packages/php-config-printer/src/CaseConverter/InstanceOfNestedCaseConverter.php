<?php

declare(strict_types=1);

namespace Symplify\PhpConfigPrinter\CaseConverter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use Symplify\PhpConfigPrinter\NodeFactory\CommonNodeFactory;
use Symplify\PhpConfigPrinter\NodeFactory\Service\ServiceOptionNodeFactory;
use Symplify\PhpConfigPrinter\ValueObject\MethodName;
use Symplify\PhpConfigPrinter\ValueObject\VariableName;
use Symplify\PhpConfigPrinter\ValueObject\YamlKey;

final class InstanceOfNestedCaseConverter
{
    public function __construct(
        private CommonNodeFactory $commonNodeFactory,
        private ServiceOptionNodeFactory $serviceOptionNodeFactory
    ) {
    }

    public function convertToMethodCall(string $key, array $values): Expression
    {
        $classConstFetch = $this->commonNodeFactory->createClassReference($key);

        $servicesVariable = new Variable(VariableName::SERVICES);
        $args = [new Arg($classConstFetch)];

        $instanceofMethodCall = new MethodCall($servicesVariable, MethodName::INSTANCEOF, $args);

        $decoreatedInstanceofMethodCall = $this->serviceOptionNodeFactory->convertServiceOptionsToNodes(
            $values,
            $instanceofMethodCall
        );

        return new Expression($decoreatedInstanceofMethodCall);
    }

    public function isMatch(string $rootKey, $subKey): bool
    {
        if ($rootKey !== YamlKey::SERVICES) {
            return false;
        }

        if (! is_string($subKey)) {
            return false;
        }

        return $subKey === YamlKey::_INSTANCEOF;
    }
}
