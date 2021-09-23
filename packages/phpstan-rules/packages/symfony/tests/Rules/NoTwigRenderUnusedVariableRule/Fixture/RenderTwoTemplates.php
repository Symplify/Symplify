<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\Tests\Rules\NoTwigRenderUnusedVariableRule\Fixture;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class RenderTwoTemplates extends AbstractController
{
    public function __invoke($value): Response
    {
        $templatePath = $value ? __DIR__ . '/../Source/some_template.twig' : __DIR__ . '/../Source/another_blank_template.twig';

        return $this->render($templatePath, [
            'unused_variable' => 'some_value'
        ]);
    }
}
