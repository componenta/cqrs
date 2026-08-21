<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Attribute;

use Attribute;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsCommandListener
{
    /**
     * @param non-empty-string $command Command class or logical command name.
     * @param list<class-string<CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent>> $eventTypes
     */
    public function __construct(
        public string $command,
        public int $priority = 0,
        public array $eventTypes = [],
    ) {
        if (trim($this->command) === '') {
            throw new InvalidArgumentException(
                'CQRS command listener command name cannot be empty or whitespace.',
            );
        }

        self::assertEventTypes($this->eventTypes);
    }

    /**
     * @param array<array-key, mixed> $eventTypes
     * @throws InvalidArgumentException
     */
    public static function assertEventTypes(array $eventTypes): void
    {
        if (!array_is_list($eventTypes)) {
            throw new InvalidArgumentException('CQRS command listener event types must be a list.');
        }

        $supported = [
            CommandProcessEvent::class,
            CommandProcessedEvent::class,
            CommandFailedEvent::class,
        ];

        foreach ($eventTypes as $eventType) {
            if (!is_string($eventType) || !\in_array($eventType, $supported, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Event type "%s" is not supported. Supported types: %s.',
                    is_string($eventType) ? $eventType : get_debug_type($eventType),
                    implode(', ', $supported),
                ));
            }
        }
    }
}
