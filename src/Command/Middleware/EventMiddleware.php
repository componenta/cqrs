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

/** Dispatches command lifecycle events after authorization middleware. */
#[MiddlewareOrder(after: [PolicyMiddleware::class])]
final readonly class EventMiddleware implements MiddlewareInterface
{
    /**
     * Locator and configuration failures always propagate. When suppression is
     * enabled, only failures thrown by an individual listener body are ignored.
     */
    public function __construct(
        private CommandListenersLocatorInterface $locator,
        private bool $suppressExceptions = false,
    ) {}

    /** @throws Throwable */
    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        $this->dispatch(new CommandProcessEvent($operation));

        try {
            $operation = $handler->handle($operation);
        } catch (Throwable $exception) {
            try {
                $this->dispatch(new CommandFailedEvent($operation, $exception));
            } catch (Throwable $notificationFailure) {
                throw new CommandFailureNotificationException($exception, $notificationFailure);
            }

            throw $exception;
        }

        $this->dispatch(new CommandProcessedEvent($operation));

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
