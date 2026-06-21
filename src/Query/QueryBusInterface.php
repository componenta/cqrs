<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

use Componenta\CQRS\Query\Context\ContextInterface;

interface QueryBusInterface
{
    /**
     * Dispatches a query and returns the handler result.
     *
     * @param object $query The query to handle.
     * @param ContextInterface|array<string, mixed> $context Per-call context for middleware
     *        Arrays are promoted to the default {@see Context\Context} at dispatch time;
     *        middleware always receives a {@see ContextInterface}.
     */
    public function handle(object $query, ContextInterface|array $context = []): mixed;
}


