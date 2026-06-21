<?php

namespace Componenta\CQRS\Query\Resolver;

use Componenta\CQRS\Query\NamedQueryInterface;

final class QueryNameResolver implements QueryNameResolverInterface
{
    public function supports(object $query): bool
    {
        return true;
    }

    public function resolve(object $query): string
    {
        return $query instanceof NamedQueryInterface
            ? $query->queryName
            : $query::class;
    }
}
