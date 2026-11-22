<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Metadata\ReflectionCommandMetadataProvider;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class LeadingSlashTestMetadata {}

#[LeadingSlashTestMetadata]
final readonly class LeadingSlashTestCommand {}

it('resolves an attribute named with a leading namespace separator', function (): void {
    $provider = new ReflectionCommandMetadataProvider();

    expect($provider->get(new LeadingSlashTestCommand(), '\\' . LeadingSlashTestMetadata::class))
        ->toBeInstanceOf(LeadingSlashTestMetadata::class);
});
