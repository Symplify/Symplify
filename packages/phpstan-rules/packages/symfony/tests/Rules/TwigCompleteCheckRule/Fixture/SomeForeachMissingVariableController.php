<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\Tests\Rules\TwigCompleteCheckRule\Fixture;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symplify\PHPStanRules\Symfony\Tests\Rules\TwigCompleteCheckRule\Source\SomeType;

final class SomeForeachMissingVariableController extends AbstractController
{
    public function __invoke(): Response
    {
        $templateFilePath = __DIR__ . '/../Source/non_existing_method_foreach.twig';

        $someVariable = new SomeType();
        $someTypes = [$someVariable];

        return $this->render($templateFilePath, [
            'some_types' => $someTypes
        ]);
    }
}
