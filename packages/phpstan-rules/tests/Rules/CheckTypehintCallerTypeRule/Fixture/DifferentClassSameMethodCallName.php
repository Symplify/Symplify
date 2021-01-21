<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\CheckTypehintCallerTypeRule\Fixture;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use Symplify\PHPStanRules\Tests\Rules\CheckTypehintCallerTypeRule\Source\AnotherClassWithRun;
use Symplify\PHPStanRules\Tests\Rules\PreferredClassRule\Fixture\SomeStaticCall;

class DifferentClassSameMethodCallName
{
    /**
     * @param SomeStaticCall|MethodCall $node
     */
    public function process(AnotherClassWithRun $anotherClassWithRun)
    {
        $anotherClassWithRun->run($anotherClassWithRun);
    }

    /**
     * @param SomeStaticCall|MethodCall $node
     */
    private function run(Node $node)
    {
        if ($node->name instanceof MethodCall) {
            $this->run($node->name);
        }
    }
}
