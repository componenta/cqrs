<?php

declare(strict_types=1);

namespace Componenta\CQRS\Benchmarks;

use Attribute;
use Componenta\CQRS\Command\Metadata\ReflectionCommandMetadataProvider;
use PhpBench\Attributes as Bench;
use stdClass;

#[Bench\Iterations(7)]
#[Bench\Revs(10000)]
#[Bench\Warmup(100)]
final class MetadataBench
{
    private ReflectionCommandMetadataProvider $provider;
    private MetadataBenchCommand $command;
    private stdClass $withoutMetadata;

    public function prepare(): void
    {
        $this->provider = new ReflectionCommandMetadataProvider();
        $this->command = new MetadataBenchCommand();
        $this->withoutMetadata = new stdClass();
        $first = $this->provider->get($this->command, MetadataBenchAttribute::class);
        $second = $this->provider->get($this->command, MetadataBenchAttribute::class);
        if (!$first instanceof MetadataBenchAttribute
            || !$second instanceof MetadataBenchAttribute
            || $first === $second || $first->payload === $second->payload
            || $this->provider->get($this->withoutMetadata, MetadataBenchAttribute::class) !== null) {
            throw new \RuntimeException('Invalid metadata benchmark fixture.');
        }
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchPresentRepeated(): void
    {
        $this->provider->get($this->command, MetadataBenchAttribute::class);
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchAbsentRepeated(): void
    {
        $this->provider->get($this->withoutMetadata, MetadataBenchAttribute::class);
    }

    public function benchPresentFreshProvider(): void
    {
        (new ReflectionCommandMetadataProvider())->get(MetadataBenchCommand::class, MetadataBenchAttribute::class);
    }

    public function benchAbsentFreshProvider(): void
    {
        (new ReflectionCommandMetadataProvider())->get(stdClass::class, MetadataBenchAttribute::class);
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchUnavailableClass(): void
    {
        $this->provider->get('CqrsBenchmarkUnavailableCommand', MetadataBenchAttribute::class);
    }
}

#[Attribute(Attribute::TARGET_CLASS)]
final class MetadataBenchAttribute
{
    public function __construct(public object $payload) {}
}

#[MetadataBenchAttribute(new stdClass())]
final class MetadataBenchCommand {}
