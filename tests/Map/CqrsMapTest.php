<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\CommandListenerDescriptor;
use Componenta\CQRS\Map\CommandMetadataDescriptor;
use Componenta\CQRS\Map\ConfigCqrsMapProvider;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\Exception\CqrsMapConflictException;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\CQRS\Map\Exception\LegacyCqrsMapException;
use Componenta\CQRS\Map\Exception\MissingCqrsMapException;
use Componenta\CQRS\Map\HandlerDescriptor;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CqrsMapTestMetadata
{
    public function __construct(public string $value)
    {
    }
}

describe('CqrsMap', function (): void {
    it('exports a compact deterministic versioned artifact and round-trips it', function (): void {
        $map = new CqrsMap(
            commandHandlers: [
                'Command.Z' => new HandlerDescriptor('handler.z', 'handle'),
                'Command.A' => new HandlerDescriptor('handler.a', '__invoke'),
            ],
            queryHandlers: [
                'Query.Z' => new HandlerDescriptor('query.z', 'handle'),
                'Query.A' => new HandlerDescriptor('query.a', '__invoke'),
            ],
            commandListeners: [
                'Command.A' => [
                    new CommandListenerDescriptor(
                        'listener.low',
                        [CommandProcessedEvent::class],
                        -100,
                    ),
                    new CommandListenerDescriptor(
                        'listener.high',
                        [CommandFailedEvent::class, CommandProcessedEvent::class],
                        100,
                    ),
                ],
            ],
            commandMetadata: [
                'Command.A' => [
                    CqrsMapTestMetadata::class => new CommandMetadataDescriptor(
                        CqrsMapTestMetadata::class,
                        ['value' => 'compiled'],
                    ),
                ],
            ],
        );

        $artifact = $map->toArray();

        expect(array_keys($artifact))->toBe(['version', 'commands', 'queries'])
            ->and(array_keys($artifact['commands']['handlers']))->toBe(['Command.A', 'Command.Z'])
            ->and(array_column($artifact['commands']['listeners']['Command.A'], 'service'))
            ->toBe(['listener.high', 'listener.low'])
            ->and($artifact['commands']['known'])->toBe([
                'Command.A' => true,
                'Command.Z' => true,
            ])
            ->and(array_keys($artifact['queries']['handlers']))->toBe(['Query.A', 'Query.Z'])
            ->and(CqrsMap::fromArray($artifact)->toArray())->toBe($artifact);
    });

    it('omits every empty section while preserving the schema version', function (): void {
        expect(CqrsMap::empty()->toArray())->toBe(['version' => CqrsMap::VERSION])
            ->and(CqrsMap::fromArray(['version' => CqrsMap::VERSION])->toArray())
            ->toBe(['version' => CqrsMap::VERSION]);
    });

    it('merges identical descriptors, sorts listeners globally, and unions known commands', function (): void {
        $base = new CqrsMap(
            commandHandlers: [
                'Command.A' => new HandlerDescriptor('handler', '__invoke'),
            ],
            commandListeners: [
                'Command.A' => [
                    new CommandListenerDescriptor('listener.low', [], -100),
                ],
            ],
        );
        $overlay = new CqrsMap(
            commandHandlers: [
                'Command.A' => new HandlerDescriptor('handler', '__invoke'),
            ],
            commandListeners: [
                'Command.A' => [
                    new CommandListenerDescriptor('listener.high', [], 100),
                    new CommandListenerDescriptor('listener.low', [], -100),
                ],
            ],
            knownCommands: ['Command.B' => true],
        );

        $merged = $base->merge($overlay)->toArray();

        expect(array_column($merged['commands']['listeners']['Command.A'], 'service'))
            ->toBe(['listener.high', 'listener.low'])
            ->and($merged['commands']['known'])->toBe([
                'Command.A' => true,
                'Command.B' => true,
            ]);
    });

    it('rejects handler and metadata conflicts instead of silently overriding them', function (): void {
        $handlerBase = new CqrsMap(commandHandlers: [
            'Command.A' => new HandlerDescriptor('handler.a', '__invoke'),
        ]);
        $handlerOverlay = new CqrsMap(commandHandlers: [
            'Command.A' => new HandlerDescriptor('handler.b', '__invoke'),
        ]);
        $metadataBase = new CqrsMap(commandMetadata: [
            'Command.A' => [
                CqrsMapTestMetadata::class => new CommandMetadataDescriptor(
                    CqrsMapTestMetadata::class,
                    ['value' => 'a'],
                ),
            ],
        ]);
        $metadataOverlay = new CqrsMap(commandMetadata: [
            'Command.A' => [
                CqrsMapTestMetadata::class => new CommandMetadataDescriptor(
                    CqrsMapTestMetadata::class,
                    ['value' => 'b'],
                ),
            ],
        ]);

        expect(fn(): CqrsMap => $handlerBase->merge($handlerOverlay))
            ->toThrow(CqrsMapConflictException::class)
            ->and(fn(): CqrsMap => $metadataBase->merge($metadataOverlay))
            ->toThrow(CqrsMapConflictException::class);
    });

    it('rejects stale versions, legacy keys, malformed descriptors, and unsupported events', function (): void {
        $legacyKey = ConfigKey::legacyMapKeys()[0];

        expect(fn(): CqrsMap => CqrsMap::fromArray([]))
            ->toThrow(InvalidCqrsMapException::class, 'version is missing')
            ->and(fn(): CqrsMap => CqrsMap::fromArray(['version' => 1]))
            ->toThrow(InvalidCqrsMapException::class, 'Unsupported CQRS map version')
            ->and(fn(): CqrsMap => CqrsMap::fromArray([$legacyKey => []]))
            ->toThrow(LegacyCqrsMapException::class, 'app:build')
            ->and(fn(): CqrsMap => CqrsMap::fromArray([
                'version' => CqrsMap::VERSION,
                'commands' => [
                    'handlers' => [
                        'Command.A' => ['service' => '', 'method' => '__invoke'],
                    ],
                ],
            ]))->toThrow(InvalidCqrsMapException::class)
            ->and(fn(): CommandListenerDescriptor => new CommandListenerDescriptor(
                'listener',
                [stdClass::class],
            ))->toThrow(InvalidCqrsMapException::class);
    });
});

describe('ConfigCqrsMapProvider', function (): void {
    it('loads and memoizes the configured artifact', function (): void {
        $provider = new ConfigCqrsMapProvider(new Config([
            ConfigKey::CQRS_MAP => [
                'version' => CqrsMap::VERSION,
                'commands' => [
                    'handlers' => [
                        'Command.A' => [
                            'service' => 'handler',
                            'method' => '__invoke',
                        ],
                    ],
                ],
            ],
        ]));

        $map = $provider->map();

        expect($map->commandHandler('Command.A')?->service)->toBe('handler')
            ->and($provider->map())->toBe($map);
    });

    it('allows a missing artifact only in development and requires it outside development', function (): void {
        $development = new ConfigCqrsMapProvider(new Config(
            [],
            new Environment(['APP_ENV' => 'development']),
        ));
        $staging = new ConfigCqrsMapProvider(new Config(
            [],
            new Environment(['APP_ENV' => 'staging']),
        ));

        expect($development->map()->toArray())->toBe(['version' => CqrsMap::VERSION])
            ->and(fn(): CqrsMap => $staging->map())
            ->toThrow(MissingCqrsMapException::class, 'app:build');
    });

    it('rejects legacy cache keys before attempting to dispatch', function (): void {
        $legacyKey = ConfigKey::legacyMapKeys()[0];
        $provider = new ConfigCqrsMapProvider(new Config([$legacyKey => []]));

        expect(fn(): CqrsMap => $provider->map())
            ->toThrow(LegacyCqrsMapException::class, 'Clear the application cache');
    });
});
