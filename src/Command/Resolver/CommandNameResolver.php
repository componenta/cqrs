<?php

namespace Componenta\CQRS\Command\Resolver;

use Componenta\CQRS\Command\NamedCommandInterface;

final class CommandNameResolver implements CommandNameResolverInterface
{
    public function supports(object $command): bool
    {
        return true;
    }

    public function resolve(object $command): string
    {
        return $command instanceof NamedCommandInterface ? $command->commandName : $command::class;
    }
}