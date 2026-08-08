<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

use Componenta\CQRS\Command\Attribute\AsCommandListener;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use InvalidArgumentException;

final readonly class CommandListenerDescriptor
{
    public string $service;

    /** @var list<non-empty-string> */
    public array $events;

    public int $priority;

    /**
     * @param array<array-key, mixed> $events
     */
    public function __construct(string $service, array $events = [], int $priority = 0)
    {
        if (trim($service) === '') {
            throw new InvalidCqrsMapException('CQRS listener service id must be a non-empty string.');
        }

        if (!array_is_list($events)) {
            throw new InvalidCqrsMapException('CQRS listener events must be a list.');
        }

        foreach ($events as $event) {
            if (!is_string($event) || trim($event) === '') {
                throw new InvalidCqrsMapException(
                    'Every CQRS listener event type must be a non-empty class-string.',
                );
            }
        }

        $events = array_values(array_unique($events));
        sort($events);

        try {
            AsCommandListener::assertEventTypes($events);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidCqrsMapException($exception->getMessage(), previous: $exception);
        }

        $this->service = $service;
        $this->events = $events;
        $this->priority = $priority;
    }

    /**
     * @param array<array-key, mixed> $descriptor
     */
    public static function fromArray(array $descriptor): self
    {
        $keys = array_keys($descriptor);
        $expected = ['events', 'priority', 'service'];
        sort($keys);

        if ($keys !== $expected
            || !is_string($descriptor['service'])
            || !is_array($descriptor['events'])
            || !is_int($descriptor['priority'])
        ) {
            throw new InvalidCqrsMapException(
                'CQRS listener descriptor must contain string "service", list "events", and int "priority".',
            );
        }

        return new self($descriptor['service'], $descriptor['events'], $descriptor['priority']);
    }

    /**
     * @return array{service: string, events: list<non-empty-string>, priority: int}
     */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'events' => $this->events,
            'priority' => $this->priority,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->service === $other->service
            && $this->events === $other->events
            && $this->priority === $other->priority;
    }
}
