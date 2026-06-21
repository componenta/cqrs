<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\Event\CommandEvent;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\OperationInterface;
use Throwable;

/**
 * Dispatches events during command lifecycle.
 *
 * Events:
 * - CommandProcessEvent: Before execution
 * - CommandProcessedEvent: After successful execution
 * - CommandFailedEvent: On exception (before rethrow)
 *
 * @example
 * ```php
 * // Production: ignore listener failures
 * $middleware = new EventMiddleware($locator);
 *
 * // Development: fail fast on listener errors
 * $middleware = new EventMiddleware($locator, suppressExceptions: false);
 * ```
 */
final readonly class EventMiddleware implements MiddlewareInterface
{
    /**
     * @param CommandListenersLocatorInterface $locator Locates listeners for events
     * @param bool $suppressExceptions If true, exceptions from locator and listeners
     *        are silently ignored. If false, exceptions propagate and interrupt
     *        command execution. Default: true (production-safe).
     */
    public function __construct(
        private CommandListenersLocatorInterface $locator,
        private bool $suppressExceptions = true,
    ) {}

    /**
     * @throws Throwable
     */
    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        try {
            $this->dispatch(new CommandProcessEvent($operation));

            $operation = $handler->handle($operation);

            $this->dispatch(new CommandProcessedEvent($operation));
        } catch (Throwable $exception) {
            $this->dispatch(new CommandFailedEvent($operation, $exception));

            throw $exception;
        }

        return $operation;
    }

    /**
     * @throws Throwable
     */
    private function dispatch(CommandEvent $event): void
    {
        try {
            foreach ($this->locator->locateFor($event) as $listener) {
                try {
                    $listener->handleEvent($event);
                } catch (Throwable $e) {
                    if (!$this->suppressExceptions) {
                        throw $e;
                    }
                }

                if ($event->isPropagationStopped) {
                    return;
                }
            }
        } catch (Throwable $e) {
            if (!$this->suppressExceptions) {
                throw $e;
            }
        }
    }
}