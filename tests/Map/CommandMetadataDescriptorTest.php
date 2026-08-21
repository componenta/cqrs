<?php

declare(strict_types=1);

use Componenta\CQRS\Map\CommandMetadataDescriptor;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\Exception\CqrsMapConflictException;

final readonly class MetadataObjectValue
{
    public function __construct(
        public int $id,
        public array $options = [],
    ) {}
}

it('treats separately materialized equivalent metadata objects as equal', function (): void {
    $left = new CommandMetadataDescriptor('Meta', [
        'value' => new MetadataObjectValue(1, ['enabled' => true]),
    ]);
    $right = new CommandMetadataDescriptor('Meta', [
        'value' => new MetadataObjectValue(1, ['enabled' => true]),
    ]);

    expect($left->equals($right))->toBeTrue();
});

it('treats named constructor arguments as order-independent', function (): void {
    $left = new CommandMetadataDescriptor('Meta', [
        'first' => 1,
        'second' => 2,
    ]);
    $right = new CommandMetadataDescriptor('Meta', [
        'second' => 2,
        'first' => 1,
    ]);

    expect($left->equals($right))->toBeTrue();
});

it('keeps positional argument order and nested array order significant', function (): void {
    $base = new CommandMetadataDescriptor('Meta', [
        0 => 'first',
        1 => 'second',
        'options' => ['a' => 1, 'b' => 2],
    ]);

    expect($base->equals(new CommandMetadataDescriptor('Meta', [
        0 => 'second',
        1 => 'first',
        'options' => ['a' => 1, 'b' => 2],
    ])))->toBeFalse()
        ->and($base->equals(new CommandMetadataDescriptor('Meta', [
            0 => 'first',
            1 => 'second',
            'options' => ['b' => 2, 'a' => 1],
        ])))->toBeFalse();
});

it('keeps exact scalar types, float bits, and object state when comparing metadata', function (): void {
    $base = new CommandMetadataDescriptor('Meta', [
        'value' => new MetadataObjectValue(1),
        'number' => 1,
        'zero' => -0.0,
    ]);

    expect($base->equals(new CommandMetadataDescriptor('Meta', [
        'value' => new MetadataObjectValue(2),
        'number' => 1,
        'zero' => -0.0,
    ])))->toBeFalse()
        ->and($base->equals(new CommandMetadataDescriptor('Meta', [
            'value' => new MetadataObjectValue(1),
            'number' => 1.0,
            'zero' => -0.0,
        ])))->toBeFalse()
        ->and($base->equals(new CommandMetadataDescriptor('Meta', [
            'value' => new MetadataObjectValue(1),
            'number' => 1,
            'zero' => 0.0,
        ])))->toBeFalse();
});

it('merges equivalent object-valued metadata without a false conflict', function (): void {
    $base = new CqrsMap(commandMetadata: [
        'Command.A' => [
            'Meta' => new CommandMetadataDescriptor('Meta', [
                'value' => new MetadataObjectValue(1),
            ]),
        ],
    ]);
    $overlay = new CqrsMap(commandMetadata: [
        'Command.A' => [
            'Meta' => new CommandMetadataDescriptor('Meta', [
                'value' => new MetadataObjectValue(1),
            ]),
        ],
    ]);

    expect($base->merge($overlay)->commandMetadata('Command.A', 'Meta'))
        ->not->toBeNull();
});

it('still rejects genuinely different object-valued metadata', function (): void {
    $base = new CqrsMap(commandMetadata: [
        'Command.A' => [
            'Meta' => new CommandMetadataDescriptor('Meta', [
                'value' => new MetadataObjectValue(1),
            ]),
        ],
    ]);
    $overlay = new CqrsMap(commandMetadata: [
        'Command.A' => [
            'Meta' => new CommandMetadataDescriptor('Meta', [
                'value' => new MetadataObjectValue(2),
            ]),
        ],
    ]);

    expect(fn() => $base->merge($overlay))
        ->toThrow(CqrsMapConflictException::class);
});
