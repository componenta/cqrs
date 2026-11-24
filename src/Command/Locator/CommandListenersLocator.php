<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Exception\InvalidListenerException;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Psr\Container\ContainerInterface;
use Componenta\CQRS\Internal\RegistrationNormalizer;

final class CommandListenersLocator implements CommandListenersLocatorInterface
{
    use CommandNameResolution;

    /** @var array<array-key, array<string, list<string>>> */
    private readonly array $listeners;

    /** @param array<array-key, mixed> $listeners */
    public function __construct(
        array $listeners,
        private readonly ContainerInterface $container,
        ?CommandNameResolverInterface $resolver = null,
    ) {
        $byEvent = [];
        foreach (RegistrationNormalizer::listeners($listeners) as $message => $descriptors) {
            foreach ($descriptors as $descriptor) {
                $events = $descriptor['events'] === []
                    ? [CommandProcessEvent::class, CommandProcessedEvent::class, CommandFailedEvent::class]
                    : $descriptor['events'];
                foreach ($events as $event) {
                    $byEvent[$message][$event][] = $descriptor['service'];
                }
            }
        }
        $this->listeners = $byEvent;
        $this->resolver = $resolver;
    }

    public function locateFor(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): iterable {
        $commandName = $this->resolveCommandName($event->operation->command);
        foreach ($this->listeners[$commandName][$event::class] ?? [] as $service) {
            $listener = $this->container->get($service);

            if (!$listener instanceof CommandListenerInterface) {
                throw InvalidListenerException::serviceType(
                    $service,
                    get_debug_type($listener),
                );
            }

            yield $listener;
        }
    }
}
