<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Event;

interface CommandListenerInterface
{
    /**
     * Handles a command event.
     */
    public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void;
}
