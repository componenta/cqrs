<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationFactory;
use Componenta\CQRS\Command\OperationResult;
use Componenta\Identity\ComparableUuidInterface;
use Componenta\Identity\ComparisonResult;
use Componenta\Identity\RfcUuidInterface;
use Componenta\Identity\Uuid;
use Componenta\Identity\UuidFactoryInterface;
use Componenta\Identity\UuidInterface;
use Psr\Clock\ClockInterface;

it('preserves an explicitly supplied operation timestamp and minimal UUID by identity', function (): void {
    $time = new DateTimeImmutable('2025-02-03 04:05:06.123456', new DateTimeZone('Asia/Tokyo'));
    $id = new readonly class implements UuidInterface {
        public function toString(): string { return '018f6d5d-3f7a-7a9b-8c2f-123456789abc'; }
        public function __toString(): string { return $this->toString(); }
        public function equals(UuidInterface $other): bool { return $this->toString() === $other->toString(); }
    };
    $command = new stdClass();
    $operation = Operation::create($command, ['trace' => 1], $time, $id);
    $derived = $operation->withAttribute('trace', 2)
        ->withAttributes(['x' => 3])->withoutAttribute('x')
        ->withResult(new OperationResult(null, $time));

    foreach ([$operation, $derived] as $value) {
        expect($value->id)->toBe($id)
            ->and($value->createdAt)->toBe($time)
            ->and($value->command)->toBe($command);
    }
    expect($operation->result)->toBeNull()
        ->and($operation->attributes)->toBe(['trace' => 1])
        ->and($derived->result?->processedAt)->toBe($time);
});

it('defaults to current UTC and Identity UUIDv7 in standalone and factory creation', function (): void {
    $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $factory = new OperationFactory();
    $first = $factory->create(new stdClass());
    $second = $factory->create(new stdClass());
    $standalone = Operation::create(new stdClass());
    $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    foreach ([$first, $second, $standalone] as $operation) {
        expect($operation->createdAt)->toBeGreaterThanOrEqual($before)
            ->and($operation->createdAt)->toBeLessThanOrEqual($after)
            ->and($operation->createdAt->getTimezone()->getName())->toBe('UTC')
            ->and($operation->id)->toBeInstanceOf(RfcUuidInterface::class)
            ->and($operation->id->version)->toBe(7)
            ->and($operation->result)->toBeNull();
    }

    expect($first->id)->toBeInstanceOf(ComparableUuidInterface::class)
        ->and($first->id->compareTo($second->id))->toBe(ComparisonResult::Less);
});

it('reads the clock and generator once per creation and preserves the returned objects', function (): void {
    $time = new DateTimeImmutable('2025-02-03 04:05:06.123456', new DateTimeZone('Asia/Tokyo'));
    $clock = new class ($time) implements ClockInterface {
        public int $calls = 0;
        public function __construct(private readonly DateTimeImmutable $time) {}
        public function now(): DateTimeImmutable { ++$this->calls; return $this->time; }
    };
    $id = Uuid::fromString('018f6d5d-3f7a-7a9b-8c2f-123456789abc');
    $generator = new class ($id) implements UuidFactoryInterface {
        public int $calls = 0;
        public function __construct(private readonly UuidInterface $id) {}
        public function generate(): UuidInterface { ++$this->calls; return $this->id; }
        public function fromBytes(string $bytes): RfcUuidInterface { throw new LogicException('Unexpected conversion'); }
    };

    $factory = new OperationFactory($clock, $generator);
    $operation = $factory->create(new stdClass());
    expect($operation->createdAt)->toBe($time)
        ->and($operation->id)->toBe($id)
        ->and($clock->calls)->toBe(1)
        ->and($generator->calls)->toBe(1);

    $operation->withAttribute('trace', 1);
    expect($clock->calls)->toBe(1)->and($generator->calls)->toBe(1);
});

it('does not generate an identifier after a clock failure', function (): void {
    $failure = new RuntimeException('clock failed');
    $clock = new class ($failure) implements ClockInterface {
        public function __construct(private readonly Throwable $failure) {}
        public function now(): DateTimeImmutable { throw $this->failure; }
    };
    $generator = new class implements UuidFactoryInterface {
        public int $calls = 0;
        public function generate(): UuidInterface { ++$this->calls; throw new LogicException('Unexpected generation'); }
        public function fromBytes(string $bytes): RfcUuidInterface { throw new LogicException('Unexpected conversion'); }
    };

    try {
        (new OperationFactory($clock, $generator))->create(new stdClass());
        test()->fail('Expected the original clock failure.');
    } catch (RuntimeException $caught) {
        expect($caught)->toBe($failure);
    }
    expect($generator->calls)->toBe(0);
});
