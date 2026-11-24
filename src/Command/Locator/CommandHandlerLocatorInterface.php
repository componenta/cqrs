<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Exception\HandlerNotFoundException;
use Componenta\CQRS\Command\Exception\InvalidHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/** Locates the handler for a command; does not execute it. */
interface CommandHandlerLocatorInterface
{
    /**
     * Returns the callable selected for the command instance.
     *
     * The first parameter accepts the command; the configured invoker may
     * resolve additional required arguments. Absence of a handler is not
     * represented by null. A matching registration does not guarantee
     * that the handler's dependencies or invocation arguments can be resolved.
     *
     * @throws HandlerNotFoundException If no handler is registered for the resolved name.
     * @throws InvalidHandlerException If the mapped service is not a callable matching its descriptor.
     * @throws ContainerExceptionInterface If the mapped service cannot be resolved.
     * @throws Throwable Other failures from name or service resolution.
     */
    public function locateFor(object $command): callable;
}
