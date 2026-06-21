<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;

interface CommandListenersLocatorInterface
{
    /**
     * Returns all listeners registered for the command carried by the given event
     * and applicable to its type.
     *
     * @return iterable<CommandListenerInterface>
     */
    public function locateFor(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): iterable;
}