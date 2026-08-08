<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\CQRS\Command\Factory\CommandMetadataProviderFactory;
use Componenta\CQRS\Command\Metadata\CommandMetadataProviderInterface;
use Componenta\CQRS\Command\Metadata\CompiledCommandMetadataProvider;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\ConfigCqrsMapProvider;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\CQRS\Tests\Fixture\FakeContainer;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CqrsCompiledMetadata
{
    public function __construct(
        public string $transport,
        public int $delay = 0,
    ) {
    }
}

#[CqrsCompiledMetadata('reflection', 9)]
final readonly class CqrsCompiledUnknownCommand
{
}

final readonly class CqrsCompiledAnnotatedCommand
{
}

final readonly class CqrsCompiledPlainCommand
{
}

final class CqrsCountingCommandMetadataProvider implements CommandMetadataProviderInterface
{
    public int $calls = 0;

    public function __construct(private readonly ?object $metadata = null)
    {
    }

    public function get(object|string $command, string $attribute): ?object
    {
        ++$this->calls;

        return $this->metadata;
    }

    public function isKnown(object|string $command): bool
    {
        return false;
    }
}

function compiledCommandMetadataMapProviderForTests(): CqrsMapProviderInterface
{
    return new ConfigCqrsMapProvider(new Config([
        ConfigKey::CQRS_MAP => [
            'version' => 2,
            'commands' => [
                'known' => [
                    CqrsCompiledAnnotatedCommand::class => true,
                    CqrsCompiledPlainCommand::class => true,
                ],
                'metadata' => [
                    CqrsCompiledAnnotatedCommand::class => [
                        CqrsCompiledMetadata::class => [
                            'arguments' => [
                                'transport' => 'compiled',
                                'delay' => 7,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]));
}

describe('Command metadata provider', function (): void {
    it('hydrates generic attributes from compiled descriptors and caches them', function (): void {
        $provider = new CompiledCommandMetadataProvider(
            compiledCommandMetadataMapProviderForTests(),
        );

        $metadata = $provider->get(
            CqrsCompiledAnnotatedCommand::class,
            CqrsCompiledMetadata::class,
        );

        expect($metadata)->toBeInstanceOf(CqrsCompiledMetadata::class)
            ->and($metadata->transport)->toBe('compiled')
            ->and($metadata->delay)->toBe(7)
            ->and($provider->get(
                CqrsCompiledAnnotatedCommand::class,
                CqrsCompiledMetadata::class,
            ))->toBe($metadata)
            ->and($provider->isKnown(CqrsCompiledAnnotatedCommand::class))->toBeTrue();
    });

    it('does not call reflection fallback for a known command without metadata', function (): void {
        $fallback = new CqrsCountingCommandMetadataProvider(
            new CqrsCompiledMetadata('fallback'),
        );
        $provider = new CompiledCommandMetadataProvider(
            compiledCommandMetadataMapProviderForTests(),
            $fallback,
        );

        expect($provider->get(
            CqrsCompiledPlainCommand::class,
            CqrsCompiledMetadata::class,
        ))->toBeNull()
            ->and($fallback->calls)->toBe(0);
    });

    it('uses reflection fallback only for commands outside the compiled map', function (): void {
        $provider = new CompiledCommandMetadataProvider(
            compiledCommandMetadataMapProviderForTests(),
        );

        $metadata = $provider->get(
            CqrsCompiledUnknownCommand::class,
            CqrsCompiledMetadata::class,
        );

        expect($metadata)->toBeInstanceOf(CqrsCompiledMetadata::class)
            ->and($metadata->transport)->toBe('reflection')
            ->and($metadata->delay)->toBe(9)
            ->and($provider->isKnown(CqrsCompiledUnknownCommand::class))->toBeFalse();
    });

    it('rejects metadata classes that are not PHP attributes', function (): void {
        $provider = new ConfigCqrsMapProvider(new Config([
            ConfigKey::CQRS_MAP => [
                'version' => 2,
                'commands' => [
                    'metadata' => [
                        CqrsCompiledAnnotatedCommand::class => [
                            stdClass::class => ['arguments' => []],
                        ],
                    ],
                ],
            ],
        ]));
        $metadata = new CompiledCommandMetadataProvider($provider);

        expect(fn(): ?object => $metadata->get(
            CqrsCompiledAnnotatedCommand::class,
            stdClass::class,
        ))->toThrow(InvalidCqrsMapException::class, 'not declared with #[Attribute]');
    });

    it('builds the generic provider from the shared map provider service', function (): void {
        $mapProvider = compiledCommandMetadataMapProviderForTests();
        $provider = (new CommandMetadataProviderFactory())(new FakeContainer([
            CqrsMapProviderInterface::class => $mapProvider,
        ]));

        expect($provider)->toBeInstanceOf(CompiledCommandMetadataProvider::class)
            ->and($provider->get(
                CqrsCompiledAnnotatedCommand::class,
                CqrsCompiledMetadata::class,
            ))->toBeInstanceOf(CqrsCompiledMetadata::class);
    });
});
