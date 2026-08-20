<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Metadata\CommandMetadataProviderInterface;
use Componenta\CQRS\Command\Metadata\CompiledCommandMetadataProvider;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use LogicException;
use Psr\Container\ContainerInterface;

final class CommandMetadataProviderFactory
{
    public function __invoke(ContainerInterface $container): CommandMetadataProviderInterface
    {
        $mapProvider = $container->get(CqrsMapProviderInterface::class);

        if (!$mapProvider instanceof CqrsMapProviderInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                CqrsMapProviderInterface::class,
                CqrsMapProviderInterface::class,
            ));
        }

        return new CompiledCommandMetadataProvider($mapProvider);
    }
}
