<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\Tests\Rules\TwigCompleteCheckRule\Fixture;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symplify\PHPStanRules\Symfony\Tests\Rules\TwigCompleteCheckRule\Source\SomeArrayAccesType;

final class SkipExistingArrayAccessItems extends AbstractController
{
    public const FILE = __FILE__;

    public function __invoke()
    {
        $someArrayAccess = new SomeArrayAccesType();

        return $this->render(__DIR__ . '/../Source/skip_existing_array_access_items.twig', [
            'some_array_access_type' => $someArrayAccess
        ]);
    }
}
