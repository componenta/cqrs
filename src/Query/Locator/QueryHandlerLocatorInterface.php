<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Locator;

use Componenta\CQRS\Query\Exception\HandlerNotFoundException;
use Componenta\CQRS\Query\Exception\InvalidHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/** Locates the handler for a query; does not execute it. */
interface QueryHandlerLocatorInterface
{
    /**
     * Returns the callable selected for the query instance.
     *
     * The first parameter accepts the query; the configured invoker may
     * resolve additional required arguments. Absence of a handler is not
     * represented by null. A matching registration does not guarantee
     * that the handler's dependencies or invocation arguments can be resolved.
     *
     * @throws HandlerNotFoundException If no handler is registered for the resolved name.
     * @throws InvalidHandlerException If the mapped service is not a callable matching its descriptor.
     * @throws ContainerExceptionInterface If the mapped service cannot be resolved.
     * @throws Throwable Other failures from name or service resolution.
     */
    public function locateFor(object $query): callable;
}
