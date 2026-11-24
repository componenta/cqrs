<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Clock\UtcClock;
use Componenta\CQRS\Command\OperationFactory;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Componenta\Identity\UuidFactory;
use Componenta\Identity\UuidFactoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class OperationFactoryFactory
{
    public function __invoke(ContainerInterface $container): OperationFactory
    {
        $clock = $container->has(ClockInterface::class)
            ? $container->get(ClockInterface::class)
            : new UtcClock();

        if (!$clock instanceof ClockInterface) {
            throw new InvalidConfigurationException(sprintf(
                'Container entry "%s" must implement %s.',
                ClockInterface::class,
                ClockInterface::class,
            ));
        }

        $uuidFactory = $container->has(UuidFactoryInterface::class)
            ? $container->get(UuidFactoryInterface::class)
            : new UuidFactory();

        if (!$uuidFactory instanceof UuidFactoryInterface) {
            throw new InvalidConfigurationException(sprintf(
                'Container entry "%s" must implement %s.',
                UuidFactoryInterface::class,
                UuidFactoryInterface::class,
            ));
        }

        return new OperationFactory($clock, $uuidFactory);
    }
}
