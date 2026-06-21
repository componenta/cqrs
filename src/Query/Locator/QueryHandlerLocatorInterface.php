<?php

namespace Componenta\CQRS\Query\Locator;

use Componenta\CQRS\Query\Exception\HandlerNotFoundException;

interface QueryHandlerLocatorInterface
{
    /**
     * Locate handler for a given query.
     *
     * @template T of object
     * @param T $query
     * @return callable(T): mixed
     *
     * @throws HandlerNotFoundException
     */
    public function locateFor(object $query): callable;
}
