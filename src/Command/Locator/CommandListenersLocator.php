<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Exception\InvalidListenerException;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Psr\Container\ContainerInterface;

final class CommandListenersLocator implements CommandListenersLocatorInterface
{
    use CommandNameResolution;

    /** @var array<string, CommandListenerInterface> */
    private array $resolvedListeners = [];

    public function __construct(
        private readonly CqrsMapProviderInterface $mapProvider,
        private readonly ContainerInterface $container,
        ?CommandNameResolverInterface $resolver = null,
    ) {
        $this->resolver = $resolver;
    }

    public function locateFor(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): iterable {
        $commandName = $this->resolveCommandName($event->operation->command);
        $eventType = $event::class;

        foreach ($this->mapProvider->map()->commandListeners($commandName) as $descriptor) {
            if ($descriptor->events !== []
                && !in_array($eventType, $descriptor->events, true)
            ) {
                continue;
            }

            if (isset($this->resolvedListeners[$descriptor->service])) {
                yield $this->resolvedListeners[$descriptor->service];
                continue;
            }

            $listener = $this->container->get($descriptor->service);

            if (!$listener instanceof CommandListenerInterface) {
                throw InvalidListenerException::serviceType(
                    $descriptor->service,
                    get_debug_type($listener),
                );
            }

            $this->resolvedListeners[$descriptor->service] = $listener;

            yield $listener;
        }
    }
}
