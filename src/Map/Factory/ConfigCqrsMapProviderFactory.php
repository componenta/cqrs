<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\ConfigCqrsMapProvider;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Psr\Container\ContainerInterface;

final class ConfigCqrsMapProviderFactory
{
    public function __invoke(ContainerInterface $container): CqrsMapProviderInterface
    {
        $config = $container->get(ConfigKey::CONFIG);

        if (!$config instanceof Config) {
            throw new \LogicException(sprintf(
                'Container entry "%s" must be a %s instance.',
                ConfigKey::CONFIG,
                Config::class,
            ));
        }

        return new ConfigCqrsMapProvider($config);
    }
}
