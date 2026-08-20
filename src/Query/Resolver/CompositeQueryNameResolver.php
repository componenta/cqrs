<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Resolver;

final class CompositeQueryNameResolver implements QueryNameResolverInterface
{
    /** @var list<QueryNameResolverInterface> */
    private array $resolvers;

    public function __construct(
        private QueryNameResolverInterface $fallback,
        QueryNameResolverInterface ...$resolvers,
    ) {
        $this->resolvers = $resolvers;
    }

    public function supports(object $query): bool
    {
        return array_any(
            $this->resolvers,
            static fn(QueryNameResolverInterface $resolver): bool => $resolver->supports($query),
        ) || $this->fallback->supports($query);
    }

    public function resolve(object $query): string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($query)) {
                return $resolver->resolve($query);
            }
        }

        return $this->fallback->resolve($query);
    }
}
