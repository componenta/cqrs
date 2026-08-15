<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\NamedCommandInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;

trait CommandNameResolution
{
    private ?CommandNameResolverInterface $resolver = null;

    private function resolveCommandName(object $command): string
    {
        if ($this->resolver !== null && $this->resolver->supports($command)) {
            return $this->resolver->resolve($command);
        }

        return $command instanceof NamedCommandInterface ? $command->commandName : $command::class;
    }
}