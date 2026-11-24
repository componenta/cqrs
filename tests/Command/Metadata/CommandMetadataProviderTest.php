<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Exception\InvalidCommandMetadataException;
use Componenta\CQRS\Command\Factory\CommandMetadataProviderFactory;
use Componenta\CQRS\Command\Metadata\ReflectionCommandMetadataProvider;
use Componenta\CQRS\Tests\Fixture\FakeContainer;

final class MetadataPayload
{
    public string $value = 'initial';
}
#[Attribute(Attribute::TARGET_CLASS)]
final class MetadataWithObject
{
    public function __construct(public MetadataPayload $payload)
    {
    }
}
#[MetadataWithObject(new MetadataPayload())]
final class MetadataCommand
{
}
#[Attribute(Attribute::TARGET_METHOD)]
final class MethodOnlyMetadata
{
}
#[Attribute(Attribute::TARGET_CLASS)]
final class ThrowingMetadata
{
    public function __construct()
    {
        throw new DomainException('metadata failed');
    }
}
#[ThrowingMetadata]
final class ThrowingMetadataCommand
{
}
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RepeatedMetadata
{
}
#[RepeatedMetadata, RepeatedMetadata]
final class RepeatedMetadataCommand
{
}

it('reads metadata without any command registration and creates fresh nested objects on each lookup', function (): void {
    $provider = (new CommandMetadataProviderFactory())(new FakeContainer([]));
    $first = $provider->get(new MetadataCommand(), MetadataWithObject::class);
    $first->payload->value = 'changed';
    $second = $provider->get(MetadataCommand::class, MetadataWithObject::class);

    expect($second->payload->value)->toBe('initial')
        ->and($second)->not->toBe($first)
        ->and($second->payload)->not->toBe($first->payload);
});

it('returns null when a command has no requested metadata', function (): void {
    expect((new ReflectionCommandMetadataProvider())->get(new stdClass(), MetadataWithObject::class))->toBeNull();
});

it('rejects invalid metadata attribute declarations', function (string $attribute, string $message): void {
    expect(fn () => (new ReflectionCommandMetadataProvider())->get(MetadataCommand::class, $attribute))
        ->toThrow(InvalidCommandMetadataException::class, $message);
})->with([
    ['MissingMetadataClass', 'does not exist'],
    [stdClass::class, '#[Attribute]'],
    [MethodOnlyMetadata::class, 'class targets'],
]);

it('rejects repeated metadata instead of selecting an arbitrary instance', function (): void {
    expect(fn () => (new ReflectionCommandMetadataProvider())->get(RepeatedMetadataCommand::class, RepeatedMetadata::class))
        ->toThrow(InvalidCommandMetadataException::class, 'repeated');
});

it('preserves the original metadata constructor error', function (): void {
    try {
        (new ReflectionCommandMetadataProvider())->get(ThrowingMetadataCommand::class, ThrowingMetadata::class);
        test()->fail('Metadata constructor must fail.');
    } catch (InvalidCommandMetadataException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(DomainException::class)
            ->and($exception->getPrevious()->getMessage())->toBe('metadata failed');
    }
});


it('returns null for an unavailable command class', function (): void {
    expect((new ReflectionCommandMetadataProvider())->get('MissingCommandForMetadataLookup', MetadataWithObject::class))
        ->toBeNull();
});


#[Attribute(Attribute::TARGET_CLASS)]
final class LateAvailableMetadataTarget
{
}

#[CqrsLateAvailableMetadataAlias]
final class LateAvailableMetadataCommand
{
}

#[Attribute(Attribute::TARGET_CLASS)]
final class RetriedMetadata
{
    public static bool $fail = true;

    public function __construct()
    {
        if (self::$fail) {
            throw new DomainException('temporary metadata failure');
        }
    }
}

#[RetriedMetadata]
final class RetriedMetadataCommand
{
}

it('does not treat an unavailable command class as permanently absent', function (): void {
    $provider = new ReflectionCommandMetadataProvider();
    $alias = 'CqrsMetadataCommandLateAlias';
    expect($provider->get($alias, MetadataWithObject::class))->toBeNull();

    class_alias(MetadataCommand::class, $alias);

    expect($provider->get($alias, MetadataWithObject::class))->toBeInstanceOf(MetadataWithObject::class);
});

it('rechecks an attribute class that was not yet available', function (): void {
    $provider = new ReflectionCommandMetadataProvider();

    expect(fn () => $provider->get(LateAvailableMetadataCommand::class, 'CqrsLateAvailableMetadataAlias'))
        ->toThrow(InvalidCommandMetadataException::class, 'does not exist');

    class_alias(LateAvailableMetadataTarget::class, 'CqrsLateAvailableMetadataAlias');

    expect($provider->get(LateAvailableMetadataCommand::class, 'CqrsLateAvailableMetadataAlias'))
        ->toBeInstanceOf(LateAvailableMetadataTarget::class);
});

it('retries metadata construction after a previous constructor failure', function (): void {
    $provider = new ReflectionCommandMetadataProvider();
    RetriedMetadata::$fail = true;

    try {
        expect(fn () => $provider->get(RetriedMetadataCommand::class, RetriedMetadata::class))
            ->toThrow(InvalidCommandMetadataException::class, 'temporary metadata failure');

        RetriedMetadata::$fail = false;
        $first = $provider->get(RetriedMetadataCommand::class, RetriedMetadata::class);
        $second = $provider->get(RetriedMetadataCommand::class, RetriedMetadata::class);

        expect($first)->toBeInstanceOf(RetriedMetadata::class)
            ->and($second)->toBeInstanceOf(RetriedMetadata::class)
            ->and($second)->not->toBe($first);
    } finally {
        RetriedMetadata::$fail = true;
    }
});

it('keeps constructor failures observable even after successful metadata lookup', function (): void {
    $provider = new ReflectionCommandMetadataProvider();
    RetriedMetadata::$fail = false;

    try {
        expect($provider->get(RetriedMetadataCommand::class, RetriedMetadata::class))
            ->toBeInstanceOf(RetriedMetadata::class);

        RetriedMetadata::$fail = true;
        expect(fn () => $provider->get(RetriedMetadataCommand::class, RetriedMetadata::class))
            ->toThrow(InvalidCommandMetadataException::class, 'temporary metadata failure');
    } finally {
        RetriedMetadata::$fail = true;
    }
});

it('preserves exact attribute-name matching across repeated lookups', function (): void {
    $provider = new ReflectionCommandMetadataProvider();
    $alias = 'CqrsMetadataExactNameAlias';
    class_alias(MetadataWithObject::class, $alias);

    expect($provider->get(MetadataCommand::class, MetadataWithObject::class))
        ->toBeInstanceOf(MetadataWithObject::class)
        ->and($provider->get(MetadataCommand::class, $alias))->toBeNull()
        ->and($provider->get(MetadataCommand::class, strtolower(MetadataWithObject::class)))
        ->toBeInstanceOf(MetadataWithObject::class)
        ->and($provider->get(MetadataCommand::class, '\\' . MetadataWithObject::class))
        ->toBeInstanceOf(MetadataWithObject::class);
});
