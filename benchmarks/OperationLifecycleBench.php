<?php

declare(strict_types=1);

namespace Componenta\CQRS\Benchmarks;

use Componenta\CQRS\Clock\UtcClock;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationFactory;
use Componenta\CQRS\Command\OperationResult;
use Componenta\Identity\UuidFactory;
use Componenta\Identity\UuidInterface;
use DateTimeImmutable;
use PhpBench\Attributes as Bench;
use Psr\Clock\ClockInterface;
use stdClass;

#[Bench\BeforeMethods('prepare')]
#[Bench\Iterations(7)]
#[Bench\Revs(5000)]
#[Bench\Warmup(100)]
final class OperationLifecycleBench
{
    private object $command;
    private DateTimeImmutable $time;
    private UuidInterface $id;
    private Operation $operation;
    private ClockInterface $clock;
    private UtcClock $utcClock;
    private UuidFactory $generator;
    private OperationFactory $defaultFactory;
    private OperationFactory $configuredFactory;

    public function prepare(): void
    {
        $this->command = new stdClass();
        $this->time = new DateTimeImmutable('2025-01-01T10:00:00.123456+09:00');
        $this->generator = new UuidFactory();
        $this->id = $this->generator->generate();
        $this->operation = Operation::create($this->command, createdAt: $this->time, id: $this->id);
        $this->clock = new readonly class ($this->time) implements ClockInterface {
            public function __construct(private DateTimeImmutable $time) {}
            public function now(): DateTimeImmutable { return $this->time; }
        };
        $this->utcClock = new UtcClock();
        $this->defaultFactory = new OperationFactory();
        $this->configuredFactory = new OperationFactory($this->clock, $this->generator);
        $event = new CommandProcessEvent($this->operation, $this->clock);
        $event->stop();
        $first = $this->configuredFactory->create($this->command);
        $second = $this->configuredFactory->create($this->command);
        if ($first->createdAt !== $this->time
            || $first->id->equals($second->id)
            || $this->operation->id !== $this->id
            || $event->propagationStoppedAt !== $this->time
            || $event->dispatchedAt !== $this->time
            || $this->utcClock->now()->getTimezone()->getName() !== 'UTC') {
            throw new \RuntimeException('Invalid operation lifecycle fixture.');
        }
    }

    public function benchStandaloneOperation(): void
    {
        Operation::create($this->command);
    }

    public function benchOperationWithSuppliedValues(): void
    {
        Operation::create($this->command, createdAt: $this->time, id: $this->id);
    }

    public function benchDefaultOperationFactory(): void
    {
        $this->defaultFactory->create($this->command);
    }

    public function benchConfiguredOperationFactory(): void
    {
        $this->configuredFactory->create($this->command);
    }

    public function benchReusableUuidGenerator(): void
    {
        $this->generator->generate();
    }

    public function benchFreshUuidGenerator(): void
    {
        (new UuidFactory())->generate();
    }

    public function benchUtcClock(): void
    {
        $this->utcClock->now();
    }

    public function benchDefaultResult(): void
    {
        new OperationResult('done');
    }

    public function benchExplicitResult(): void
    {
        new OperationResult('done', $this->time);
    }

    public function benchDefaultEventAndFirstStop(): void
    {
        (new CommandProcessEvent($this->operation))->stop();
    }

    public function benchConfiguredEventAndFirstStop(): void
    {
        (new CommandProcessEvent($this->operation, $this->clock))->stop();
    }
}
