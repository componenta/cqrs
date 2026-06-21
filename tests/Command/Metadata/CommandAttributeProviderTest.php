<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Attribute\Async;
use Componenta\CQRS\Command\Attribute\Lock;
use Componenta\CQRS\Command\Attribute\Retry;
use Componenta\Config\Config;
use Componenta\CQRS\Command\Factory\CommandAttributeProviderFactory;
use Componenta\CQRS\Command\Metadata\CommandAttributeProviderInterface;
use Componenta\CQRS\Command\Metadata\CompiledCommandAttributeProvider;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Tests\Fixture\FakeContainer;

#[Async(transport: 'emails', delay: 7)]
#[Retry(attempts: 4, delayMs: 50, multiplier: 2.0, maxDelayMs: 500)]
#[Lock('post:{id}', ttl: 12.5, blocking: false)]
final readonly class CqrsCompiledAnnotatedCommand
{
    public function __construct(public int $id = 1) {}
}

final readonly class CqrsCompiledPlainCommand {}

final readonly class CqrsCompiledUnknownCommand {}

final class CqrsCountingCommandAttributeProvider implements CommandAttributeProviderInterface
{
    public int $asyncCalls = 0;
    public int $retryCalls = 0;
    public int $lockCalls = 0;

    public function __construct(
        private readonly ?Async $async = null,
        private readonly ?Retry $retry = null,
        private readonly ?Lock $lock = null,
    ) {}

    public function async(object|string $command): ?Async
    {
        $this->asyncCalls++;

        return $this->async;
    }

    public function retry(object|string $command): ?Retry
    {
        $this->retryCalls++;

        return $this->retry;
    }

    public function lock(object|string $command): ?Lock
    {
        $this->lockCalls++;

        return $this->lock;
    }
}

function compiledCommandAttributeMapForTests(): array
{
    return [
        'known' => [
            CqrsCompiledAnnotatedCommand::class => true,
            CqrsCompiledPlainCommand::class => true,
        ],
        'attributes' => [
            CqrsCompiledAnnotatedCommand::class => [
                'async' => [
                    'transport' => 'emails',
                    'delay' => 7,
                ],
                'retry' => [
                    'attempts' => 4,
                    'delayMs' => 50,
                    'multiplier' => 2.0,
                    'maxDelayMs' => 500,
                ],
                'lock' => [
                    'key' => 'post:{id}',
                    'ttl' => 12.5,
                    'blocking' => false,
                ],
            ],
        ],
    ];
}

describe('Command metadata provider', function () {
    it('hydrates attributes from compiled descriptors and caches them per class', function () {
        $provider = new CompiledCommandAttributeProvider(compiledCommandAttributeMapForTests());

        $async = $provider->async(CqrsCompiledAnnotatedCommand::class);
        $retry = $provider->retry(CqrsCompiledAnnotatedCommand::class);
        $lock = $provider->lock(CqrsCompiledAnnotatedCommand::class);

        expect($async)->toBeInstanceOf(Async::class)
            ->and($async?->transport)->toBe('emails')
            ->and($retry)->toBeInstanceOf(Retry::class)
            ->and($retry?->attempts)->toBe(4)
            ->and($lock)->toBeInstanceOf(Lock::class)
            ->and($lock?->blocking)->toBeFalse()
            ->and($provider->async(CqrsCompiledAnnotatedCommand::class))->toBe($async)
            ->and($provider->retry(CqrsCompiledAnnotatedCommand::class))->toBe($retry)
            ->and($provider->lock(CqrsCompiledAnnotatedCommand::class))->toBe($lock);
    });

    it('does not call fallback for known commands without command attributes', function () {
        $fallback = new CqrsCountingCommandAttributeProvider(
            async: new Async('fallback'),
            retry: new Retry(),
            lock: new Lock('fallback'),
        );
        $provider = new CompiledCommandAttributeProvider(compiledCommandAttributeMapForTests(), $fallback);

        expect($provider->async(CqrsCompiledPlainCommand::class))->toBeNull()
            ->and($provider->retry(CqrsCompiledPlainCommand::class))->toBeNull()
            ->and($provider->lock(CqrsCompiledPlainCommand::class))->toBeNull()
            ->and($fallback->asyncCalls)->toBe(0)
            ->and($fallback->retryCalls)->toBe(0)
            ->and($fallback->lockCalls)->toBe(0);
    });

    it('falls back to reflection provider for classes outside the compiled command set', function () {
        $fallback = new CqrsCountingCommandAttributeProvider(async: new Async('fallback', 3));
        $provider = new CompiledCommandAttributeProvider(compiledCommandAttributeMapForTests(), $fallback);

        $async = $provider->async(CqrsCompiledUnknownCommand::class);

        expect($async)->toBeInstanceOf(Async::class)
            ->and($async?->transport)->toBe('fallback')
            ->and($async?->delay)->toBe(3)
            ->and($fallback->asyncCalls)->toBe(1);
    });

    it('can disable compiled command metadata maps through config', function () {
        $container = new FakeContainer([
            ConfigKey::CONFIG => new Config([
                ConfigKey::COMMAND_ATTRIBUTE_MAP => compiledCommandAttributeMapForTests(),
                ConfigKey::COMPILED_MAPS => false,
            ]),
        ]);

        $provider = (new CommandAttributeProviderFactory())($container);

        expect($provider)->not->toBeInstanceOf(CompiledCommandAttributeProvider::class)
            ->and($provider->async(CqrsCompiledAnnotatedCommand::class))->toBeInstanceOf(Async::class);
    });

    it('uses compiled command metadata maps by default', function () {
        $container = new FakeContainer([
            ConfigKey::CONFIG => new Config([
                ConfigKey::COMMAND_ATTRIBUTE_MAP => compiledCommandAttributeMapForTests(),
            ]),
        ]);

        $provider = (new CommandAttributeProviderFactory())($container);

        expect($provider)->toBeInstanceOf(CompiledCommandAttributeProvider::class);
    });
});
