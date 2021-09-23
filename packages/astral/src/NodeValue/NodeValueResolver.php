<?php

declare(strict_types=1);

namespace Symplify\Astral\NodeValue;

use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\UnionType;
use ReflectionClassConstant;
use Symplify\Astral\Exception\ShouldNotHappenException;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\Astral\NodeFinder\SimpleNodeFinder;
use Symplify\PackageBuilder\Php\TypeChecker;

/**
 * @see \Symplify\Astral\Tests\NodeValue\NodeValueResolverTest
 */
final class NodeValueResolver
{
    private ConstExprEvaluator $constExprEvaluator;

    private ?string $currentFilePath = null;

    private UnionTypeValueResolver $unionTypeValueResolver;

    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private TypeChecker $typeChecker,
        private SimpleNodeFinder $simpleNodeFinder
    ) {
        $this->constExprEvaluator = new ConstExprEvaluator(fn (Expr $expr) => $this->resolveByNode($expr));
        $this->unionTypeValueResolver = new UnionTypeValueResolver();
    }

    /**
     * @return array|bool|float|int|mixed|string|null
     */
    public function resolveWithScope(Expr $expr, Scope $scope)
    {
        $this->currentFilePath = $scope->getFile();

        try {
            return $this->constExprEvaluator->evaluateDirectly($expr);
        } catch (ConstExprEvaluationException) {
        }

        $exprType = $scope->getType($expr);
        if ($exprType instanceof ConstantScalarType) {
            return $exprType->getValue();
        }

        if ($exprType instanceof UnionType) {
            return $this->unionTypeValueResolver->resolveConstantTypes($exprType);
        }

        return null;
    }

    /**
     * @return array|bool|float|int|mixed|string|null
     */
    public function resolve(Expr $expr, string $filePath)
    {
        $this->currentFilePath = $filePath;

        try {
            return $this->constExprEvaluator->evaluateDirectly($expr);
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }

    /**
     * @return mixed|null
     */
    private function resolveClassConstFetch(ClassConstFetch $classConstFetch)
    {
        $className = $this->simpleNameResolver->getName($classConstFetch->class);

        if ($className === 'self') {
            $classLike = $this->simpleNodeFinder->findFirstParentByType($classConstFetch, ClassLike::class);
            if (! $classLike instanceof ClassLike) {
                return null;
            }
            $className = $this->simpleNameResolver->getName($classLike);
        }

        if ($className === null) {
            return null;
        }

        $constantName = $this->simpleNameResolver->getName($classConstFetch->name);
        if ($constantName === null) {
            return null;
        }

        if ($constantName === 'class') {
            return $className;
        }

        if (! class_exists($className) && ! interface_exists($className)) {
            return null;
        }

        $reflectionClassConstant = new ReflectionClassConstant($className, $constantName);
        return $reflectionClassConstant->getValue();
    }

    private function resolveMagicConst(MagicConst $magicConst): ?string
    {
        if ($this->currentFilePath === null) {
            throw new ShouldNotHappenException();
        }

        if ($magicConst instanceof Dir) {
            return dirname($this->currentFilePath);
        }

        if ($magicConst instanceof File) {
            return $this->currentFilePath;
        }

        return null;
    }

    /**
     * @return mixed|null
     */
    private function resolveConstFetch(ConstFetch $constFetch)
    {
        $constFetchName = $this->simpleNameResolver->getName($constFetch);
        if ($constFetchName === null) {
            return null;
        }

        return constant($constFetchName);
    }

    /**
     * @return mixed|string|int|bool|null
     */
    private function resolveByNode(Expr $expr)
    {
        if ($this->currentFilePath === null) {
            throw new ShouldNotHappenException();
        }

        if ($expr instanceof MagicConst) {
            return $this->resolveMagicConst($expr);
        }

        if ($expr instanceof FuncCall && $this->simpleNameResolver->isName($expr, 'getcwd')) {
            return dirname($this->currentFilePath);
        }

        if ($expr instanceof ConstFetch) {
            return $this->resolveConstFetch($expr);
        }

        if ($expr instanceof ClassConstFetch) {
            return $this->resolveClassConstFetch($expr);
        }

        if ($this->typeChecker->isInstanceOf(
            $expr,
            [Variable::class, Cast::class, MethodCall::class, PropertyFetch::class, Instanceof_::class]
        )) {
            throw new ConstExprEvaluationException();
        }

        return null;
    }
}
