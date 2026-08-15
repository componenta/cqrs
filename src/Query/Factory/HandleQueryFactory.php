<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Factory;

use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;
use LogicException;
use Psr\Container\ContainerInterface;

final class HandleQueryFactory
{
    public function __invoke(ContainerInterface $container): HandleQuery
    {
        $locator = $container->get(QueryHandlerLocatorInterface::class);

        if (!$locator instanceof QueryHandlerLocatorInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                QueryHandlerLocatorInterface::class,
                QueryHandlerLocatorInterface::class,
            ));
        }

        $invoker = $container->has(CallableInvokerInterface::class)
            ? $container->get(CallableInvokerInterface::class)
            : new CallableInvoker();

        if (!$invoker instanceof CallableInvokerInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                CallableInvokerInterface::class,
                CallableInvokerInterface::class,
            ));
        }

        return new HandleQuery($locator, $invoker);
    }
}