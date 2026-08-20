<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Resolver;

interface CommandNameResolverInterface
{
    public function supports(object $command): bool;

    public function resolve(object $command): string;
}
