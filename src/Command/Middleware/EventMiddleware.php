<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\Exception\CommandFailureNotificationException;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\OperationInterface;
use Throwable;
use Psr\Clock\ClockInterface;

/** Dispatches command lifecycle events around downstream command execution. */
final readonly class EventMiddleware implements MiddlewareInterface
{
    /**
     * Locator and configuration failures always propagate. When suppression is
     * enabled, only failures thrown by an individual listener body are ignored.
     */
    public function __construct(
        private CommandListenersLocatorInterface $locator,
        private bool $suppressExceptions = false,
        private ?ClockInterface $clock = null,
    ) {}

    /**
     * Emits the process event before delegation and the processed event on success.
     * A downstream failure emits the failed event before propagation. A failure in
     * the process or processed listener does not emit a second failed event.
     *
     * @throws CommandFailureNotificationException If notifying a downstream failure also fails.
     * @throws Throwable If downstream processing, lookup or an unsuppressed listener fails.
     */
    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        $this->dispatch(new CommandProcessEvent($operation, $this->clock));

        try {
            $operation = $handler->handle($operation);
        } catch (Throwable $exception) {
            try {
                $this->dispatch(new CommandFailedEvent($operation, $exception, $this->clock));
            } catch (Throwable $notificationFailure) {
                throw new CommandFailureNotificationException($exception, $notificationFailure);
            }

            throw $exception;
        }

        $this->dispatch(new CommandProcessedEvent($operation, $this->clock));

        return $operation;
    }

    /** @throws Throwable */
    private function dispatch(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): void {
        foreach ($this->locator->locateFor($event) as $listener) {
            try {
                $listener->handleEvent($event);
            } catch (Throwable $exception) {
                if (!$this->suppressExceptions) {
                    throw $exception;
                }
            }

            if ($event->isPropagationStopped) {
                return;
            }
        }
    }
}
