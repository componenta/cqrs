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

/**
 * @phpstan-import-type Handler from RegistrationNormalizer
 * @phpstan-import-type Listener from RegistrationNormalizer
 */
final class CommandListenersLocator implements CommandListenersLocatorInterface
{
    use CommandNameResolution;

    /** @var array<array-key, list<Listener>> */
    private readonly array $listeners;

    /** @param array<array-key, mixed> $listeners */
    public function __construct(
        array $listeners,
        private readonly ContainerInterface $container,
        ?CommandNameResolverInterface $resolver = null,
    ) {
        $this->listeners = RegistrationNormalizer::listeners($listeners);
        $this->resolver = $resolver;
    }

    public function locateFor(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): iterable {
        $commandName = $this->resolveCommandName($event->operation->command);
        $eventType = $event::class;

        foreach (($this->listeners[$commandName] ?? []) as $descriptor) {
            if ($descriptor['events'] !== []
                && !in_array($eventType, $descriptor['events'], true)
            ) {
                continue;
            }

            $listener = $this->container->get($descriptor['service']);

            if (!$listener instanceof CommandListenerInterface) {
                throw InvalidListenerException::serviceType(
                    $descriptor['service'],
                    get_debug_type($listener),
                );
            }

            yield $listener;
        }
    }
}
