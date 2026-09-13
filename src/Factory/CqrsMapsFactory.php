<?php

declare(strict_types=1);

namespace Componenta\CQRS\Factory;

use Componenta\Config\ContainerValue;
use Componenta\CQRS\Internal\RegistrationNormalizer;

/** @phpstan-import-type Maps from RegistrationNormalizer */
final class CqrsMapsFactory
{
    /** @return Maps */
    public function __invoke(ContainerValue $container): array
    {
        return RegistrationNormalizer::fromConfig($container->config);
    }
}
