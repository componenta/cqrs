<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Command\Exception\CommandFailureNotificationException;
use Componenta\CQRS\Command\Exception\HandlerNotFoundException;
use Componenta\CQRS\Command\Exception\InvalidHandlerException;
use Componenta\DI\Exception\ExceptionInterface as InvocationExceptionInterface;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/** Dispatches commands through the configured processing pipeline. */
interface CommandBusInterface
{
    /**
     * Dispatches one command and returns the resulting operation.
     *
     * Attributes use non-empty string keys (excluding whitespace-only names
     * and numeric names converted by PHP to integer keys). Middleware can
     * complete or defer a command without invoking its terminal handler.
     * A null operation result is valid for deferred processing.
     *
     * @param array<string, mixed> $attributes
     * @throws InvalidArgumentException If attributes contain invalid names.
     * @throws HandlerNotFoundException If handler lookup is reached and no handler is registered.
     * @throws InvalidHandlerException If a registered handler cannot be located as a callable.
     * @throws ContainerExceptionInterface If handler or listener service resolution fails.
     * @throws InvocationExceptionInterface If the DI invoker cannot prepare the call.
     * @throws CommandFailureNotificationException If event middleware cannot notify listeners of a downstream failure.
     * @throws Throwable Other failures from operation creation, middleware, resolution or handler execution.
     *
     * Exceptions escaping the pipeline propagate without being wrapped by the bus.
     */
    public function dispatch(object $command, array $attributes = []): OperationInterface;
}
