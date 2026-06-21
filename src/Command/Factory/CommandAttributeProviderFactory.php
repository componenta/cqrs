<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\Command\Metadata\CommandAttributeProviderInterface;
use Componenta\CQRS\Command\Metadata\CompiledCommandAttributeProvider;
use Componenta\CQRS\Command\Metadata\ReflectionCommandAttributeProvider;
use Componenta\CQRS\ConfigKey;
use Psr\Container\ContainerInterface;

final class CommandAttributeProviderFactory
{
    public function __invoke(ContainerInterface $container): CommandAttributeProviderInterface
    {
        $fallback = new ReflectionCommandAttributeProvider();

        /** @var Config $config */
        $config = $container->get(ConfigKey::CONFIG);
        $map = $config->get(ConfigKey::COMMAND_ATTRIBUTE_MAP, []);

        if (!$this->compiledMapsEnabled($config) || !is_array($map) || $map === []) {
            return $fallback;
        }

        return new CompiledCommandAttributeProvider($map, $fallback);
    }

    private function compiledMapsEnabled(Config $config): bool
    {
        return (bool) $config->get(ConfigKey::COMPILED_MAPS, true);
    }
}
