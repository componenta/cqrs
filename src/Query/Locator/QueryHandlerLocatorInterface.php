<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Locator;

use Componenta\CQRS\Query\Exception\HandlerNotFoundException;

interface QueryHandlerLocatorInterface
{
    /**
     * Locate handler for a given query.
     *
     * The first parameter accepts the query. Additional arguments may be
     * required by the handler and resolved by the configured invoker.
     *
     *
     * @throws HandlerNotFoundException
     */
    public function locateFor(object $query): callable;
}
