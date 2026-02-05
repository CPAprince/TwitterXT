<?php

declare(strict_types=1);

namespace Twitter\Shared\Infrastructure\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class DoctrineResetSubscriber implements EventSubscriberInterface
{
    public function __construct(private ManagerRegistry $registry) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->registry->resetManager();
    }
}
