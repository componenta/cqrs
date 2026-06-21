<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Event;

abstract readonly class EventListener implements CommandListenerInterface
{
    public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
    {
        match ($event::class) {
            CommandProcessEvent::class => $this->handleProcessEvent($event),
            CommandProcessedEvent::class => $this->handleProcessedEvent($event),
            CommandFailedEvent::class => $this->handleFailedEvent($event)
        };
    }

    protected function handleProcessEvent(CommandProcessEvent $event): void
    {
    }

    protected function handleProcessedEvent(CommandProcessedEvent $event): void
    {
    }

    protected function handleFailedEvent(CommandFailedEvent $event): void
    {
    }
}