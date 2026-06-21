<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Attribute\AsCommandListener;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use LogicException;
use Psr\Container\ContainerInterface;

/**
 * Plain command-listeners locator backed by a flat map.
 *
 * Each entry is a list whose items may be:
 *  - arrays in the form `{class: CommandListenerInterface, eventTypes: list<class-string>, priority: int}`
 *    (registered programmatically via {@see register()});
 *  - arrays in the form `{class: class-string, eventTypes: list<class-string>, priority: int}`
 *    (the form that {@see \Componenta\CQRS\App\Command\Locator\AttributeCommandListenersLocator::toArray()} emits and that
 *    `config.cache.php` stores). Class-strings are resolved through the container on first
 *    lookup and then memoised in-place.
 *
 * Entries within each command key are kept sorted by priority (descending) at all times,
 * so {@see locateFor()} never pays a sorting cost.
 */
final class CommandListenersLocator implements CommandListenersLocatorInterface
{
    use CommandNameResolution;

    /**
     * @param array<string, list<array{
     *     class: CommandListenerInterface|class-string<CommandListenerInterface>,
     *     eventTypes: list<class-string>,
     *     priority: int,
     * }>> $map
     */
    public function __construct(
        private array $map = [],
        ?CommandNameResolverInterface $resolver = null,
        private readonly ?ContainerInterface $container = null,
    ) {
        $this->resolver = $resolver;
    }

    /**
     * @param list<class-string> $eventTypes Empty means "all event types".
     */
    public function register(
        string $commandName,
        CommandListenerInterface $listener,
        array $eventTypes = [],
        int $priority = 0,
    ): void {
        AsCommandListener::assertEventTypes($eventTypes);

        $this->map[$commandName][] = [
            'class'      => $listener,
            'eventTypes' => $eventTypes,
            'priority'   => $priority,
        ];

        $this->sortByPriority($commandName);
    }

    public function locateFor(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): iterable
    {
        $key = $this->resolveCommandName($event->operation->command);

        if (empty($this->map[$key])) {
            return [];
        }

        $eventClass = $event::class;
        $resolved   = [];

        foreach (array_keys($this->map[$key]) as $i) {
            $entry = &$this->map[$key][$i];

            if (!\is_array($entry) || !isset($entry['class'], $entry['eventTypes'])) {
                throw new LogicException(sprintf(
                    'CommandListenersLocator: entry #%d for "%s" must be an array{class, eventTypes, priority}; got %s.',
                    $i,
                    $key,
                    get_debug_type($entry),
                ));
            }

            if (\is_string($entry['class'])) {
                if ($this->container === null) {
                    throw new LogicException(sprintf(
                        'CommandListenersLocator: cannot resolve listener "%s" for "%s" - '
                        . 'no container was supplied. Pass one in the constructor when '
                        . 'using compiled class-string entries.',
                        $entry['class'],
                        $key,
                    ));
                }

                $entry['class'] = $this->container->get($entry['class']);
            }

            if (!$entry['class'] instanceof CommandListenerInterface) {
                throw new LogicException(sprintf(
                    'CommandListenersLocator: entry #%d for "%s": field "class" must be '
                    . 'a listener instance or class-string; got %s.',
                    $i,
                    $key,
                    get_debug_type($entry['class']),
                ));
            }

            if ($entry['eventTypes'] !== [] && !\in_array($eventClass, $entry['eventTypes'], true)) {
                continue;
            }

            $resolved[] = $entry['class'];
        }

        unset($entry);

        return $resolved;
    }

    private function sortByPriority(string $commandName): void
    {
        usort(
            $this->map[$commandName],
            static fn (array $a, array $b) => $b['priority'] <=> $a['priority'],
        );
    }
}
