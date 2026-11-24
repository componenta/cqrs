<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Event;

use Throwable;

/** Receives command lifecycle events from event middleware. */
interface CommandListenerInterface
{
    /**
     * Handles one lifecycle event. Calling the event's stop() method stops
     * subsequent listeners of that event; it does not cancel the command.
     *
     * @throws Throwable If the listener fails. EventMiddleware propagates
     *                  listener failures by default; suppression is configurable.
     */
    public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void;
}
