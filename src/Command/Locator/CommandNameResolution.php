<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\NamedCommandInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;

trait CommandNameResolution
{
    private array $resolvedNames = [];

    private ?CommandNameResolverInterface $resolver = null;

    private function resolveCommandName(object $command): string
    {
        if (isset($this->resolvedNames[$command::class])) {
            return $this->resolvedNames[$command::class];
        }

        return $this->resolvedNames[$command::class] = $this->resolver?->resolve($command)
            ?? ($command instanceof NamedCommandInterface ? $command->commandName : $command::class);
    }
}