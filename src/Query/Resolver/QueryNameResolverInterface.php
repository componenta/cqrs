<?php

namespace Componenta\CQRS\Query\Resolver;

interface QueryNameResolverInterface
{
    /**
     * Can this resolver handle the given query?
     */
    public function supports(object $query): bool;

    /**
     * Resolve the query name (used for permissions, logging, handler mapping).
     */
    public function resolve(object $query): string;
}
