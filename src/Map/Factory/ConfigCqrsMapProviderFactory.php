<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map\Factory;

use Componenta\Config\ContainerValue;
use Componenta\CQRS\Map\ConfigCqrsMapProvider;
use Componenta\CQRS\Map\CqrsMapProviderInterface;

final class ConfigCqrsMapProviderFactory
{
    public function __invoke(ContainerValue $container): CqrsMapProviderInterface
    {
        return new ConfigCqrsMapProvider($container->config);
    }
}
