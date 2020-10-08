<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use Symplify\CodingStandard\ValueObject\PHPStanAttributeKey;

/**
 * @see \Symplify\CodingStandard\Tests\Rules\RequireThisOnParentMethodCallRule\RequireThisOnParentMethodCallRuleTest
 */
final class RequireThisOnParentMethodCallRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Use $this-> on parent method call unless in the same named method';

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /**
     * @param StaticCall $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Name) {
            return [];
        }

        $isParentCall = $node->class->parts[0] === 'parent';
        if (! $isParentCall) {
            return [];
        }

        $classMethod = $this->getClassMethod($node);
        if ($classMethod === null) {
            return [];
        }

        /** @var Identifier $name */
        $classMethodIdentifier = $classMethod->name;
        /** @var Identifier $name */
        $staticCallIdentifier = $node->name;

        if ((string) $classMethodIdentifier === (string) $staticCallIdentifier) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    private function getClassMethod(StaticCall $staticCall): ?ClassMethod
    {
        $classMethod = $staticCall->getAttribute(PHPStanAttributeKey::PARENT);
        while ($classMethod) {
            if ($classMethod instanceof ClassMethod) {
                break;
            }

            $classMethod = $classMethod->getAttribute(PHPStanAttributeKey::PARENT);
        }

        return $classMethod;
    }
}
