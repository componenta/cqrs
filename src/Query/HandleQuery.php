<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

use Closure;
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

    public function __invoke(object $query): mixed
    {
        $handler = $this->locator->locateFor($query);

        // Avoid a second DI service lookup by callable string name.
        // Keep private/protected static method visibility in invoker scope.
        if (is_string($handler)) {
            $handler = str_contains($handler, '::')
                ? explode('::', $handler, 2)
                : Closure::fromCallable($handler);
        }

        return $this->invoker->call($handler, [$query]);
    }
}
