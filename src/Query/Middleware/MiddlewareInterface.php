<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Middleware;

use Componenta\CQRS\Query\Context\ContextInterface;
use Throwable;

/** Intercepts query processing. */
interface MiddlewareInterface
{
    /**
     * Processes a query around the next pipeline stage.
     *
     * May pass the query and its original or derived context downstream, or
     * return a result without invoking the next stage. Null is a valid result.
     *
     * @param ContextInterface $context Per-call context propagated through the chain.
     * @param callable(object, ContextInterface): mixed $next
     * @throws Throwable If middleware or downstream processing fails and the failure is not handled.
     */
    public function handle(object $query, ContextInterface $context, callable $next): mixed;
}
