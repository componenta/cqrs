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
     * @template T of object
     * @param T $command Command to find handler for
     * @return callable(T): mixed Handler callable
     *
     * @throws HandlerNotFoundException If handler not found
     */
    public function locateFor(object $command): callable;
}