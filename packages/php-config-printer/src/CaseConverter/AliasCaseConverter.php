<?php

declare(strict_types=1);

namespace Symplify\PhpConfigPrinter\CaseConverter;

use Nette\Utils\Strings;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Symplify\PackageBuilder\Reflection\ClassLikeExistenceChecker;
use Symplify\PhpConfigPrinter\Contract\CaseConverterInterface;
use Symplify\PhpConfigPrinter\NodeFactory\ArgsNodeFactory;
use Symplify\PhpConfigPrinter\NodeFactory\CommonNodeFactory;
use Symplify\PhpConfigPrinter\NodeFactory\Service\ServiceOptionNodeFactory;
use Symplify\PhpConfigPrinter\ValueObject\MethodName;
use Symplify\PhpConfigPrinter\ValueObject\VariableName;
use Symplify\PhpConfigPrinter\ValueObject\YamlKey;
use Symplify\SymplifyKernel\Exception\ShouldNotHappenException;

final class AliasCaseConverter implements CaseConverterInterface
{
    /**
     * @see https://regex101.com/r/BwXkfO/2/
     * @var string
     */
    private const ARGUMENT_NAME_REGEX = '#\$(?<argument_name>\w+)#';

    /**
     * @see https://regex101.com/r/DDuuVM/1
     * @var string
     */
    private const NAMED_ALIAS_REGEX = '#\w+\s+\$\w+#';

    public function __construct(
        private CommonNodeFactory $commonNodeFactory,
        private ArgsNodeFactory $argsNodeFactory,
        private ServiceOptionNodeFactory $serviceOptionNodeFactory,
        private ClassLikeExistenceChecker $classLikeExistenceChecker
    ) {
    }

    public function convertToMethodCall($key, $values): Expression
    {
        if (! is_string($key)) {
            throw new ShouldNotHappenException();
        }

        $servicesVariable = new Variable(VariableName::SERVICES);
        if ($this->classLikeExistenceChecker->doesClassLikeExist($key)) {
            return $this->createFromClassLike($key, $values, $servicesVariable);
        }

        // handles: "SomeClass $someVariable: ..."
        $fullClassName = Strings::before($key, ' $');
        if ($fullClassName !== null) {
            $methodCall = $this->createAliasNode($key, $fullClassName, $values);
            return new Expression($methodCall);
        }

        if (is_string($values) && $values[0] === '@') {
            $args = $this->argsNodeFactory->createFromValues([$key, $values], true);
            $methodCall = new MethodCall($servicesVariable, MethodName::ALIAS, $args);
            return new Expression($methodCall);
        }

        if (is_array($values)) {
            return $this->createFromArrayValues($values, $key, $servicesVariable);
        }

        throw new ShouldNotHappenException();
    }

    public function match(string $rootKey, $key, $values): bool
    {
        if ($rootKey !== YamlKey::SERVICES) {
            return false;
        }

        if (isset($values[YamlKey::ALIAS])) {
            return true;
        }

        if (Strings::match($key, self::NAMED_ALIAS_REGEX)) {
            return true;
        }

        if (! is_string($values)) {
            return false;
        }

        return $values[0] === '@';
    }

    private function createAliasNode(string $key, string $fullClassName, $serviceValues): MethodCall
    {
        $args = [];

        $classConstFetch = $this->commonNodeFactory->createClassReference($fullClassName);

        Strings::match($key, self::ARGUMENT_NAME_REGEX);
        $argumentName = '$' . Strings::after($key, '$');

        $concat = new Concat($classConstFetch, new String_(' ' . $argumentName));
        $args[] = new Arg($concat);

        $serviceName = ltrim($serviceValues, '@');
        $args[] = new Arg(new String_($serviceName));

        return new MethodCall(new Variable(VariableName::SERVICES), MethodName::ALIAS, $args);
    }

    /**
     * @param mixed $values
     */
    private function createFromClassLike(string $key, $values, Variable $servicesVariable): Expression
    {
        $classReference = $this->commonNodeFactory->createClassReference($key);

        $argValues = [];
        $argValues[] = $classReference;
        $argValues[] = $values[MethodName::ALIAS] ?? $values;

        $args = $this->argsNodeFactory->createFromValues($argValues, true);
        $methodCall = new MethodCall($servicesVariable, MethodName::ALIAS, $args);

        return new Expression($methodCall);
    }

    private function createFromAlias(string $serviceName, string $key, Variable $servicesVariable): MethodCall
    {
        if ($this->classLikeExistenceChecker->doesClassLikeExist($serviceName)) {
            $classReference = $this->commonNodeFactory->createClassReference($serviceName);
            $args = $this->argsNodeFactory->createFromValues([$key, $classReference]);
        } else {
            $args = $this->argsNodeFactory->createFromValues([$key, $serviceName]);
        }

        return new MethodCall($servicesVariable, MethodName::ALIAS, $args);
    }

    /**
     * @param mixed[] $values
     */
    private function createFromArrayValues(array $values, string $key, Variable $servicesVariable): Expression
    {
        if (isset($values[MethodName::ALIAS])) {
            $methodCall = $this->createFromAlias($values[MethodName::ALIAS], $key, $servicesVariable);
            unset($values[MethodName::ALIAS]);
        } else {
            throw new ShouldNotHappenException();
        }

        /** @var MethodCall $methodCall */
        $methodCall = $this->serviceOptionNodeFactory->convertServiceOptionsToNodes($values, $methodCall);
        return new Expression($methodCall);
    }
}
