<?php

namespace Componenta\CQRS\Query\Resolver;

use Componenta\CQRS\Query\NamedQueryInterface;

trait QueryNameResolution
{
    private array $resolvedNames = [];
    private ?QueryNameResolverInterface $resolver = null;

    private function resolveQueryName(object $query): string
    {
        if (isset($this->resolvedNames[$query::class])) {
            return $this->resolvedNames[$query::class];
        }

        return $this->resolvedNames[$query::class] = $this->resolver?->resolve($query)
            ?? ($query instanceof NamedQueryInterface ? $query->queryName : $query::class);
    }
}
