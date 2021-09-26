<?php

declare(strict_types=1);

use Nette\Application\UI\Control;

/**
 * @property-read \Nette\Bridges\ApplicationLatte\Template $template
 */
final class SomeControl extends Control
{
    public function render(string $name = 'Adam'): void
    {
        $this->template->render(__DIR__ . '/some_control.latte', [
            'someVariable' => new SomeType(),
        ]);
    }

    public function createComponentSelfie(): self
    {
        return new self();
    }
}
