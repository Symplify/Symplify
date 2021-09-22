<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Printer;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Param;
use PhpParser\PrettyPrinter\Standard;
use Symplify\Astral\ValueObject\AttributeKey;

final class NodeComparator
{
    public function __construct(
        private Standard $standard
    ) {
    }

    public function areNodesEqual(Node $firstNode, Node $secondNode): bool
    {
        // remove comments from nodes
        $firstNode->setAttribute(AttributeKey::COMMENTS, null);
        $secondNode->setAttribute(AttributeKey::COMMENTS, null);

        return $this->standard->prettyPrint([$firstNode]) === $this->standard->prettyPrint([$secondNode]);
    }

    /**
     * @param array<Arg|Node\VariadicPlaceholder> $methodCallArgs
     * @param Param[] $classMethodParams
     */
    public function areArgsAndParamsSame(array $methodCallArgs, array $classMethodParams): bool
    {
        if (count($methodCallArgs) !== count($classMethodParams)) {
            return false;
        }

        foreach ($methodCallArgs as $key => $arg) {
            $param = $classMethodParams[$key];

            $argContent = $this->standard->prettyPrint([$arg]);
            $paramContent = $this->standard->prettyPrint([$param]);

            if ($argContent === $paramContent) {
                continue;
            }

            return false;
        }

        return true;
    }
}
