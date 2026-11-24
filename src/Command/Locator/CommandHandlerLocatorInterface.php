<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Exception\HandlerNotFoundException;

/**
 * Locates handler for command.
 */
interface CommandHandlerLocatorInterface
{
    /**
     * Locates handler for the given command.
     *
     * The first parameter accepts the command. Additional arguments may be
     * required by the handler and resolved by the configured invoker.
     *
     * @throws HandlerNotFoundException If handler not found
     */
    public function locateFor(object $command): callable;
}