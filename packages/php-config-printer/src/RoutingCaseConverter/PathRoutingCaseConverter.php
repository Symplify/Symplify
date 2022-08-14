<?php

declare(strict_types=1);

namespace Symplify\PhpConfigPrinter\RoutingCaseConverter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use Symplify\PhpConfigPrinter\Contract\RoutingCaseConverterInterface;
use Symplify\PhpConfigPrinter\Enum\RouteOption;
use Symplify\PhpConfigPrinter\NodeFactory\ArgsNodeFactory;
use Symplify\PhpConfigPrinter\ValueObject\VariableName;

final class PathRoutingCaseConverter implements RoutingCaseConverterInterface
{
    public function __construct(
        private ArgsNodeFactory $argsNodeFactory
    ) {
    }

    public function match(string $key, mixed $values): bool
    {
        return isset($values[RouteOption::PATH]);
    }

    public function convertToMethodCall(string $key, mixed $values): Stmt
    {
        $variable = new Variable(VariableName::ROUTING_CONFIGURATOR);

        $args = $this->createAddArgs($key, $values);
        $methodCall = new MethodCall($variable, 'add', $args);

        foreach (RouteOption::ALL as $nestedKey) {
            if (! isset($values[$nestedKey])) {
                continue;
            }

            $nestedValues = $values[$nestedKey];

            // Transform methods as string GET|HEAD to array
            if ($nestedKey === RouteOption::METHODS && is_string($nestedValues)) {
                $nestedValues = explode('|', $nestedValues);
            }

            // if default and controller, replace with controller() method
            // @see https://github.com/symfony/symfony/pull/24180/files#r141346267
            if ($this->hasControllerDefaults($nestedKey, $nestedValues)) {
                $controllerValue = $nestedValues['_controller'];

                // split to class + method for better readability
                if(str_contains($controllerValue, '::')) {
                    [$controllerClass, $controllerMethod] = explode('::', $controllerValue);
                    $controllerValue = [$controllerClass, $controllerMethod];
                }

                $args = $this->argsNodeFactory->createFromValues([$controllerValue]);
                $methodCall = new MethodCall($methodCall, 'controller', $args);

                unset($nestedValues['_controller']);
            }

            if (! is_array($nestedValues) || (is_array($nestedValues) && $nestedValues !== [])) {
                $args = $this->argsNodeFactory->createFromValues([$nestedValues]);
                $methodCall = new MethodCall($methodCall, $nestedKey, $args);
            }
        }

        return new Expression($methodCall);
    }

    /**
     * @return Arg[]
     */
    private function createAddArgs(string $key, mixed $values): array
    {
        $argumentValues = [];
        $argumentValues[] = $key;

        if (isset($values[RouteOption::PATH])) {
            $argumentValues[] = $values[RouteOption::PATH];
        }

        return $this->argsNodeFactory->createFromValues($argumentValues);
    }

    private function hasControllerDefaults(string $nestedKey, mixed $nestedValues): bool
    {
        if ($nestedKey !== RouteOption::DEFAULTS) {
            return false;
        }

        return array_key_exists('_controller', $nestedValues);
    }
}
