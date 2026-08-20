<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Resolver;

final class CompositeCommandNameResolver implements CommandNameResolverInterface
{
    /** @var list<CommandNameResolverInterface> */
    private array $resolvers;

    public function __construct(
        private CommandNameResolverInterface $fallback,
        CommandNameResolverInterface ...$resolvers,
    ) {
        $this->resolvers = $resolvers;
    }

    public function supports(object $command): bool
    {
        return array_any(
            $this->resolvers,
            static fn(CommandNameResolverInterface $resolver): bool => $resolver->supports($command),
        ) || $this->fallback->supports($command);
    }

    public function resolve(object $command): string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($command)) {
                return $resolver->resolve($command);
            }
        }

        return $this->fallback->resolve($command);
    }
}
