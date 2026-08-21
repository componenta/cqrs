<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Attribute\AsCommandListener;
use Componenta\CQRS\Command\Event\CommandProcessEvent;

it('rejects an empty command listener command name', function (string $command): void {
    expect(fn() => new AsCommandListener($command))
        ->toThrow(InvalidArgumentException::class, 'cannot be empty or whitespace');
})->with(['', '   ']);

it('rejects non-list command listener event declarations', function (): void {
    expect(fn() => new AsCommandListener(
        stdClass::class,
        eventTypes: ['event' => CommandProcessEvent::class],
    ))->toThrow(InvalidArgumentException::class, 'must be a list');
});

it('rejects non-string command listener event entries', function (): void {
    expect(fn() => new AsCommandListener(stdClass::class, eventTypes: [123]))
        ->toThrow(InvalidArgumentException::class, 'is not supported');
});
