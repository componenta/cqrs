<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

use Componenta\Config\Config;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\CQRS\Map\Exception\LegacyCqrsMapException;
use Componenta\CQRS\Map\Exception\MissingCqrsMapException;

final class ConfigCqrsMapProvider implements CqrsMapProviderInterface
{
    private ?CqrsMap $map = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function map(): CqrsMap
    {
        if ($this->map !== null) {
            return $this->map;
        }

        foreach (ConfigKey::legacyMapKeys() as $legacyKey) {
            if ($this->config->has($legacyKey)) {
                throw LegacyCqrsMapException::detected($legacyKey);
            }
        }

        if (!$this->config->has(ConfigKey::CQRS_MAP)) {
            if ($this->config->environment?->string('APP_ENV', 'development') === 'production') {
                throw MissingCqrsMapException::forProduction();
            }

            return $this->map = CqrsMap::empty();
        }

        $artifact = $this->config->get(ConfigKey::CQRS_MAP);

        if (!is_array($artifact)) {
            throw new InvalidCqrsMapException(sprintf(
                'CQRS map configuration must be an array; got %s.',
                get_debug_type($artifact),
            ));
        }

        return $this->map = CqrsMap::fromArray($artifact);
    }
}
