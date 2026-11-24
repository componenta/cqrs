<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Attribute\AsCommandListener;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Tests\Fixture\FakeContainer;

it('snapshots event type references in command listener attributes', function (): void {
    $eventType = CommandFailedEvent::class;
    $listener = new AsCommandListener('example', eventTypes: [&$eventType]);
    $eventType = CommandProcessEvent::class;

    expect($listener->eventTypes)->toBe([CommandFailedEvent::class]);

    $copy = $listener->eventTypes;
    $copy[0] = CommandProcessEvent::class;
    expect($listener->eventTypes)->toBe([CommandFailedEvent::class]);
});

it('isolates listener event filters from caller-owned references', function (): void {
    $eventType = CommandFailedEvent::class;
    $listener = new class implements CommandListenerInterface {
        public function handleEvent(
            CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
        ): void {}
    };
    $locator = new CommandListenersLocator([
        stdClass::class => [
            ['service' => 'listener', 'events' => [&$eventType]],
        ],
    ], new FakeContainer(['listener' => $listener]));
    $eventType = CommandProcessEvent::class;
    $operation = Operation::create(new stdClass());

    expect(iterator_to_array($locator->locateFor(new CommandFailedEvent($operation, new RuntimeException('failure')))))
        ->toBe([$listener])
        ->and(iterator_to_array($locator->locateFor(new CommandProcessEvent($operation))))
        ->toBe([]);
});
