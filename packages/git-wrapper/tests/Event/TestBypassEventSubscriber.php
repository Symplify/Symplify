<?php

declare(strict_types=1);

namespace Symplify\GitWrapper\Tests\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symplify\GitWrapper\Event\GitPrepareEvent;

final class TestBypassEventSubscriber implements EventSubscriberInterface
{
    /**
     * @return int[][]|string[][]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            GitPrepareEvent::class => ['onPrepare', -5],
        ];
    }

    public function onPrepare(GitPrepareEvent $gitPrepareEvent): void
    {
        $gitPrepareEvent->getCommand()
            ->bypass();
    }
}
