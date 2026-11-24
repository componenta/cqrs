<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Exception\ExceptionInterface;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Throwable;

/** Locates listeners applicable to a command lifecycle event. */
interface CommandListenersLocatorInterface
{
    /**
     * Returns applicable listeners in execution order, or an empty iterable
     * if none are registered. The default locator filters by event type
     * before resolving services, in descending priority order.
     *
     * Lazy resolution is allowed: errors may occur when iterating, not
     * only when calling this method. No listener is executed by the locator.
     *
     * @return iterable<CommandListenerInterface>
     * @throws ExceptionInterface If a registered listener violates the listener contract.
     * @throws Throwable Other failures during lookup or iteration.
     */
    public function locateFor(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): iterable;
}
