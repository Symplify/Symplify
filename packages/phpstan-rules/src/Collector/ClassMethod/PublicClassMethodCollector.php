<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Collector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;
use Symplify\PHPStanRules\PhpDoc\ApiDocStmtAnalyzer;

/**
 * @implements Collector<ClassMethod, array{class-string, string, int}|null>
 */
final class PublicClassMethodCollector implements Collector
{
    /**
     * @var string[]
     */
    private const SKIPPED_TYPES = [
        TestCase::class,
        \Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator::class,
    ];

    public function __construct(
        private ApiDocStmtAnalyzer $apiDocStmtAnalyzer
    ) {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return array<array{class-string, string, int}>|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if ($this->shouldSkipClassMethod($node)) {
            return null;
        }

        // only if the class has no parents/implementers, to avoid class method required by contracts
        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if ($this->apiDocStmtAnalyzer->isApiDoc($node, $classReflection)) {
            return null;
        }

        if ($this->skipClassReflection($classReflection)) {
            return null;
        }

        $methodName = $node->name->toString();

<<<<<<< HEAD
        // is this method required by parent contract? skip it
        foreach ($classReflection->getInterfaces() as $parentInterfaceReflection) {
            if ($parentInterfaceReflection->hasMethod($methodName)) {
                return null;
            }
<<<<<<< HEAD
=======
        if ($this->isUsedByParentClassOrInterface($classReflection, $methodName)) {
            return null;
>>>>>>> add parent test case method override
        }

        return [$classReflection->getName(), $methodName, $node->getLine()];
    }

    private function shouldSkipClassMethod(ClassMethod $classMethod): bool
    {
        if ($classMethod->isMagic()) {
            return true;
        }

        if ($classMethod->isStatic()) {
            return true;
=======
>>>>>>> [PHPStanRules] Check interface methods over the contract
        }

<<<<<<< HEAD
        return ! $classMethod->isPublic();
=======
        return [$classReflection->getName(), $methodName, $node->getLine()];
>>>>>>> [PHPStanRules] Check interface methods over the contract
    }

    private function shouldSkipClassMethod(ClassMethod $classMethod): bool
    {
        if ($classMethod->isMagic()) {
            return true;
        }

        if ($classMethod->isStatic()) {
            return true;
        }

        return ! $classMethod->isPublic();
    }

    private function skipClassReflection(ClassReflection $classReflection): bool
    {
        // skip interface as required, traits as unable to detect for sure
        if (! $classReflection->isClass()) {
            return true;
        }

        foreach (self::SKIPPED_TYPES as $skippedType) {
            if ($classReflection->isSubclassOf($skippedType)) {
                return true;
            }
        }

        return false;
    }

    private function isUsedByParentClassOrInterface(ClassReflection $classReflection, string $methodName): bool
    {
        // is this method required by parent contract? skip it
        foreach ($classReflection->getInterfaces() as $parentInterfaceReflection) {
            if ($parentInterfaceReflection->hasMethod($methodName)) {
                return true;
            }
        }

        foreach ($classReflection->getParents() as $parentClassReflection) {
            if ($parentClassReflection->hasMethod($methodName)) {
                return true;
            }
        }

        return false;
    }
}
