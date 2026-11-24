<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Resolver;

use Componenta\CQRS\Query\Exception\UnsupportedQueryException;

final class CompositeQueryNameResolver implements QueryNameResolverInterface
{
    /** @var array<int|string, QueryNameResolverInterface> */
    private array $resolvers;

    public function __construct(
        private QueryNameResolverInterface $fallback,
        QueryNameResolverInterface ...$resolvers,
    ) {
        $this->resolvers = $resolvers;
    }

    public function supports(object $query): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($query)) {
                return true;
            }
        }

        return $this->fallback->supports($query);
    }

    public function resolve(object $query): string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($query)) {
                return $resolver->resolve($query);
            }
        }

        if ($this->fallback->supports($query)) {
            return $this->fallback->resolve($query);
        }

        throw new UnsupportedQueryException(sprintf(
            'No name resolver supports query "%s".',
            $query::class,
        ));
    }
}
