<?php

declare(strict_types=1);

namespace Symplify\EasyHydrator\TypeCaster;

use ReflectionParameter;
use Symplify\EasyHydrator\ClassConstructorValuesResolver;
use Symplify\EasyHydrator\Contract\TypeCasterInterface;
use Symplify\EasyHydrator\ParameterTypeRecognizer;

final class ArrayTypeCaster implements TypeCasterInterface
{
    public function __construct(
        private ParameterTypeRecognizer $parameterTypeRecognizer
    ) {
    }

    public function isSupported(ReflectionParameter $reflectionParameter): bool
    {
        $type = $this->parameterTypeRecognizer->getType($reflectionParameter);

        return $type === 'array';
    }

    public function retype(
        $value,
        ReflectionParameter $reflectionParameter,
        ClassConstructorValuesResolver $classConstructorValuesResolver
    ): ?array {
        $type = $this->parameterTypeRecognizer->getTypeFromDocBlock($reflectionParameter);
        $arrayLevels = $this->parameterTypeRecognizer->getArrayLevels($reflectionParameter);
        if ($this->isAllowedNull($value, $reflectionParameter)) {
            return null;
        }

        $mapMultilevelArray = static function ($levels) use ($type, &$mapMultilevelArray): callable {
            return static function ($value) use ($type, &$mapMultilevelArray, $levels) {
                $arrayLevel = $levels - 1;
                if ($arrayLevel > 0) {
                    $currentMapFunction = $mapMultilevelArray($arrayLevel);
                    return array_map($currentMapFunction, $value);
                }

                if ($type === 'string') {
                    return (string) $value;
                }

                if ($type === 'bool') {
                    return (bool) $value;
                }

                if ($type === 'int') {
                    return (int) $value;
                }

                if ($type === 'float') {
                    return (float) $value;
                }

                return $value;
            };
        };

        $currentMapFunction = $mapMultilevelArray($arrayLevels);
        return array_map($currentMapFunction, $value);
    }

    public function getPriority(): int
    {
        return 8;
    }

    private function isAllowedNull($value, ReflectionParameter $reflectionParameter): bool
    {
        if ($value !== null) {
            return false;
        }

        return $reflectionParameter->allowsNull();
    }
}
