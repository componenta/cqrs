<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;

final readonly class HandleQuery
{
    public function __construct(
        private QueryHandlerLocatorInterface $locator,
        private CallableInvokerInterface $invoker = new CallableInvoker(),
    ) {
    }

    /**
     * Locates and invokes the query handler, returning its result unchanged.
     *
     * @throws \Componenta\CQRS\Query\Exception\HandlerNotFoundException
     * @throws \Componenta\CQRS\Query\Exception\InvalidHandlerException
     * @throws \Throwable Other failures from lookup or invocation.
     */
    public function __invoke(object $query): mixed
    {
        return $this->invoker->call($this->locator->locateFor($query), [$query]);
    }
}
