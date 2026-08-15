<?php

namespace Componenta\CQRS\Query\Resolver;

use Componenta\CQRS\Query\NamedQueryInterface;

trait QueryNameResolution
{
    private ?QueryNameResolverInterface $resolver = null;

    private function resolveQueryName(object $query): string
    {
        if ($this->resolver !== null && $this->resolver->supports($query)) {
            return $this->resolver->resolve($query);
        }

        return $query instanceof NamedQueryInterface ? $query->queryName : $query::class;
    }
}
