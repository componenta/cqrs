<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Exception\ExceptionInterface;
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
     * @throws ExceptionInterface If no handler can be located or its registration is invalid.
     * @throws Throwable Other failures during lookup or name resolution.
     */
    public function locateFor(object $command): callable;
}
