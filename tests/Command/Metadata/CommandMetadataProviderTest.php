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
