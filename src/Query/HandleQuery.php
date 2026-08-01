<?php

namespace Componenta\CQRS\Query;

use Componenta\CQRS\Handler\DirectHandlerCallableInterface;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;

final readonly class HandleQuery
{
    public function __construct(
        private QueryHandlerLocatorInterface $locator,
        private CallableInvokerInterface     $invoker = new CallableInvoker
    ) {
    }

    public function __invoke(object $query): mixed
    {
        $handler = $this->locator->locateFor($query);

        if ($handler instanceof DirectHandlerCallableInterface) {
            return $handler($query);
        }

        return $this->invoker->call($handler, [$query]);
    }
}