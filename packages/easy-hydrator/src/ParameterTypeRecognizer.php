<?php

declare(strict_types=1);

namespace Symplify\EasyHydrator;

use Nette\Utils\Reflection;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use ReflectionClass;
use ReflectionParameter;
use ReflectionType;
use Symplify\SimplePhpDocParser\SimplePhpDocParser;
use Symplify\SimplePhpDocParser\ValueObject\Ast\PhpDoc\SimplePhpDocNode;

/**
 * @see \Symplify\EasyHydrator\Tests\ParameterTypeRecognizerTest
 */
final class ParameterTypeRecognizer
{
    public function __construct(
        private SimplePhpDocParser $simplePhpDocParser
    ) {
    }

    public function isArray(ReflectionParameter $reflectionParameter): bool
    {
        $type = $this->getTypeFromTypeHint($reflectionParameter);

        if ($type === 'array') {
            return true;
        }

        $docNode = $this->getDocNode($reflectionParameter);
        if (! $docNode instanceof SimplePhpDocNode) {
            return false;
        }

        $typeNode = $docNode->getParamType($reflectionParameter->getName());
        if ($typeNode instanceof ArrayTypeNode) {
            return true;
        }

        return $typeNode instanceof GenericTypeNode;
    }

    public function getType(ReflectionParameter $reflectionParameter): ?string
    {
        $type = $this->getTypeFromTypeHint($reflectionParameter);
        if ($type) {
            return $type;
        }

        return $this->getTypeFromDocBlock($reflectionParameter);
    }

    public function isParameterOfClass(ReflectionParameter $reflectionParameter, string $class): bool
    {
        $parameterType = $this->getType($reflectionParameter);

        if ($parameterType === null) {
            return false;
        }

        return is_a($parameterType, $class, true);
    }

    public function getTypeFromDocBlock(ReflectionParameter $reflectionParameter): ?string
    {
        $docNode = $this->getDocNode($reflectionParameter);

        $declaringReflectionClass = $reflectionParameter->getDeclaringClass();
        if (! $declaringReflectionClass instanceof ReflectionClass) {
            return null;
        }

        if (! $docNode instanceof SimplePhpDocNode) {
            return null;
        }

        $typeNode = $docNode->getParamType($reflectionParameter->getName());

        if ($typeNode instanceof UnionTypeNode) {
            $typeNode = $this->findFirstNonNullNodeType($typeNode);
        }

        if ($typeNode instanceof ArrayTypeNode) {
            return $this->getTypeFromArrayTypeNode($typeNode, $reflectionParameter);
        }

        if ($typeNode instanceof GenericTypeNode) {
            $genericTypeNodes = $typeNode->genericTypes;
            $genericTypeNode = $genericTypeNodes[count($genericTypeNodes) - 1];

            return Reflection::expandClassName((string) $genericTypeNode, $declaringReflectionClass);
        }

        if ($typeNode instanceof IdentifierTypeNode) {
            return Reflection::expandClassName($typeNode->name, $declaringReflectionClass);
        }

        return null;
    }

    public function getArrayLevels(ReflectionParameter $reflectionParameter): int
    {
        $docNode = $this->getDocNode($reflectionParameter);
        if (! $docNode instanceof SimplePhpDocNode) {
            return 0;
        }

        $currentTypeNode = $docNode->getParamType($reflectionParameter->getName());
        $level = 0;
        while ($currentTypeNode instanceof ArrayTypeNode) {
            ++$level;
            $currentTypeNode = $currentTypeNode->type;
        }

        return $level;
    }

    private function getTypeFromArrayTypeNode(
        ArrayTypeNode $arrayTypeNode,
        ReflectionParameter $reflectionParameter
    ): ?string {
        $declaringReflectionClass = $reflectionParameter->getDeclaringClass();
        if (! $declaringReflectionClass instanceof ReflectionClass) {
            return null;
        }

        $currentTypeNode = $arrayTypeNode;
        do {
            $identifierTypeNode = $currentTypeNode->type;
            if ($identifierTypeNode instanceof IdentifierTypeNode) {
                return Reflection::expandClassName($identifierTypeNode->name, $declaringReflectionClass);
            }

            if ($identifierTypeNode instanceof GenericTypeNode) {
                $genericTypeNodes = $identifierTypeNode->genericTypes;
                $genericTypeNode = $genericTypeNodes[count($genericTypeNodes) - 1];
                return Reflection::expandClassName((string) $genericTypeNode, $declaringReflectionClass);
            }

            $currentTypeNode = $identifierTypeNode;
        } while ($currentTypeNode instanceof ArrayTypeNode);

        return null;
    }

    private function getTypeFromTypeHint(ReflectionParameter $reflectionParameter): ?string
    {
        $parameterReflectionType = $reflectionParameter->getType();
        if (! $parameterReflectionType instanceof ReflectionType) {
            return null;
        }

        return $parameterReflectionType->getName();
    }

    private function findFirstNonNullNodeType(UnionTypeNode $unionTypeNode): ?TypeNode
    {
        foreach ($unionTypeNode->types as $innerType) {
            if ((string) $innerType !== 'null') {
                return $innerType;
            }
        }

        return null;
    }

    private function getDocNode(ReflectionParameter $reflectionParameter): ?SimplePhpDocNode
    {
        $functionReflection = $reflectionParameter->getDeclaringFunction();
        $docComment = $functionReflection->getDocComment();

        if ($docComment === false) {
            return null;
        }

        return $this->simplePhpDocParser->parseDocBlock($docComment);
    }
}
