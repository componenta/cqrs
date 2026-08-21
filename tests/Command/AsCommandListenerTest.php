<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Attribute\AsCommandListener;

it('rejects an empty command listener command name', function (string $command): void {
    expect(fn() => new AsCommandListener($command))
        ->toThrow(InvalidArgumentException::class, 'cannot be empty or whitespace');
})->with(['', '   ']);

it('rejects non-string command listener event entries', function (): void {
    expect(fn() => new AsCommandListener(stdClass::class, eventTypes: [123]))
        ->toThrow(InvalidArgumentException::class, 'is not supported');
});
