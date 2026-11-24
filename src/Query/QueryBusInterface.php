<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

use Componenta\CQRS\Query\Context\ContextInterface;
use Componenta\CQRS\Query\Exception\HandlerNotFoundException;
use Componenta\CQRS\Query\Exception\InvalidHandlerException;
use Componenta\DI\Exception\ExceptionInterface as InvocationExceptionInterface;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/** Handles queries through the configured processing pipeline. */
interface QueryBusInterface
{
    /**
     * Returns the result of the query pipeline, including null when appropriate.
     *
     * An array context is converted to the default Context. A supplied
     * ContextInterface is passed to middleware unchanged. Middleware may return
     * a result without invoking the terminal handler.
     *
     * Exceptions escaping the pipeline propagate without being wrapped by the bus.
     *
     * @param ContextInterface|array<string, mixed> $context Per-dispatch middleware attributes.
     * @throws InvalidArgumentException If array context contains invalid attribute names.
     * @throws HandlerNotFoundException If handler lookup is reached and no handler is registered.
     * @throws InvalidHandlerException If a registered handler cannot be located as a callable.
     * @throws ContainerExceptionInterface If handler service resolution fails.
     * @throws InvocationExceptionInterface If the DI invoker cannot prepare the call.
     * @throws Throwable Other failures from middleware, resolution or handler execution.
     */
    public function handle(object $query, ContextInterface|array $context = []): mixed;
}
