<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Middleware;

use Componenta\CQRS\Query\Context\ContextInterface;

interface MiddlewareInterface
{
    /**
     * @param object $query
     * @param ContextInterface $context Per-call context propagated through the chain.
     * @param callable(object, ContextInterface): mixed $next
     */
    public function handle(object $query, ContextInterface $context, callable $next): mixed;
}
