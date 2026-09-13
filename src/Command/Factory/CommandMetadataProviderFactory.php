<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Metadata\CommandMetadataProviderInterface;
use Componenta\CQRS\Command\Metadata\ReflectionCommandMetadataProvider;
use Psr\Container\ContainerInterface;

final class CommandMetadataProviderFactory
{
    public function __invoke(ContainerInterface $container): CommandMetadataProviderInterface
    {
        return new ReflectionCommandMetadataProvider();
    }
}
