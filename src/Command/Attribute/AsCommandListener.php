<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Attribute;

use Attribute;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
final readonly class AsCommandListener
{
    /**
     * @param class-string $command
     * @param list<class-string<CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent>> $eventTypes
     */
    public function __construct(
        public string $command,
        public int $priority = 0,
        public array $eventTypes = [],
    ) {
        self::assertEventTypes($this->eventTypes);
    }

    /**
     * @param list<class-string> $eventTypes
     * @throws InvalidArgumentException
     */
    public static function assertEventTypes(array $eventTypes): void
    {
        $supported = [
            CommandProcessEvent::class,
            CommandProcessedEvent::class,
            CommandFailedEvent::class,
        ];

        foreach ($eventTypes as $eventType) {
            if (!\in_array($eventType, $supported, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Event type "%s" is not supported. Supported types: %s.',
                    $eventType,
                    implode(', ', $supported),
                ));
            }
        }
    }
}