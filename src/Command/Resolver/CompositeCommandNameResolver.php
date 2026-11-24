<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Resolver;

use Componenta\CQRS\Command\Exception\UnsupportedCommandException;

final class CompositeCommandNameResolver implements CommandNameResolverInterface
{
    /** @var array<int|string, CommandNameResolverInterface> */
    private array $resolvers;

    public function __construct(
        private CommandNameResolverInterface $fallback,
        CommandNameResolverInterface ...$resolvers,
    ) {
        $this->resolvers = $resolvers;
    }

    public function supports(object $command): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($command)) {
                return true;
            }
        }

        return $this->fallback->supports($command);
    }

    public function resolve(object $command): string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($command)) {
                return $resolver->resolve($command);
            }
        }

        if ($this->fallback->supports($command)) {
            return $this->fallback->resolve($command);
        }

        throw new UnsupportedCommandException($command);
    }
}
