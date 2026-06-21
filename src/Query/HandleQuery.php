<?php

namespace Componenta\CQRS\Query;

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
        return $this->invoker->call(
            $this->locator->locateFor($query), [$query]
        );
    }
}