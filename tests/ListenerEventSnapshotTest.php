<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Attribute\AsCommandListener;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Internal\RegistrationNormalizer;

it('snapshots event type references in command listener attributes', function (): void {
    $eventType = CommandFailedEvent::class;
    $listener = new AsCommandListener('example', eventTypes: [&$eventType]);
    $eventType = CommandProcessEvent::class;

    expect($listener->eventTypes)->toBe([CommandFailedEvent::class]);

    $copy = $listener->eventTypes;
    $copy[0] = CommandProcessEvent::class;
    expect($listener->eventTypes)->toBe([CommandFailedEvent::class]);
});

it('snapshots and deduplicates event types in normalized listener maps', function (): void {
    $eventType = CommandFailedEvent::class;
    $map = RegistrationNormalizer::listeners([
        'example' => [
            ['service' => 'listener', 'events' => [&$eventType, CommandFailedEvent::class], 'priority' => 10],
        ],
    ]);

    $eventType = CommandProcessEvent::class;
    expect($map['example'][0]['events'])->toBe([CommandFailedEvent::class]);

    $copy = $map['example'];
    $copy[0]['events'][0] = CommandProcessEvent::class;
    expect($map['example'][0]['events'])->toBe([CommandFailedEvent::class]);
});
