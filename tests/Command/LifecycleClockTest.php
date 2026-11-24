<?php

declare(strict_types=1);

use Componenta\CQRS\Clock\UtcClock;
use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Exception\CommandFailureNotificationException;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationFactory;
use Psr\Clock\ClockInterface;

final class LifecycleSequenceClock implements ClockInterface
{
    public int $calls = 0;
    /** @param list<DateTimeImmutable|Throwable> $readings */
    public function __construct(private array $readings) {}
    public function now(): DateTimeImmutable
    {
        $reading = $this->readings[$this->calls++] ?? throw new LogicException('Unexpected clock read.');
        if ($reading instanceof Throwable) {
            throw $reading;
        }
        return $reading;
    }
}

function lifecycleClockHandler(Closure $callback, ClockInterface $clock = new UtcClock()): HandleCommandHandler
{
    $locator = new readonly class ($callback) implements CommandHandlerLocatorInterface {
        public function __construct(private Closure $callback) {}
        public function locateFor(object $command): callable { return $this->callback; }
    };
    return new HandleCommandHandler($locator, clock: $clock);
}

function lifecycleClockMiddleware(Closure $callback, ClockInterface $clock = new UtcClock(), bool $suppress = false): EventMiddleware
{
    $listener = new readonly class ($callback) implements CommandListenerInterface {
        public function __construct(private Closure $callback) {}
        public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
        {
            ($this->callback)($event);
        }
    };
    $locator = new readonly class ($listener) implements CommandListenersLocatorInterface {
        public function __construct(private CommandListenerInterface $listener) {}
        public function locateFor(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): iterable
        {
            yield $this->listener;
        }
    };
    return new EventMiddleware($locator, suppressExceptions: $suppress, clock: $clock);
}

it('records lifecycle instants at each stage with the configured clock including first stop', function (): void {
    $times = [
        new DateTimeImmutable('2025-02-03 10:00:00.123456+09:00'),
        new DateTimeImmutable('2025-02-03 10:00:01.123456+09:00'),
        new DateTimeImmutable('2025-02-03 10:00:02.123456+09:00'),
        new DateTimeImmutable('2025-02-02 10:00:00.654321-05:00'),
        new DateTimeImmutable('2025-02-03 10:00:04.123456+09:00'),
    ];
    $clock = new LifecycleSequenceClock($times);
    $events = [];
    $middleware = lifecycleClockMiddleware(static function ($event) use (&$events, $clock): void {
        $events[] = $event;
        if ($event instanceof CommandProcessEvent) {
            expect($clock->calls)->toBe(2);
            $event->stop();
            $event->stop();
            expect($clock->calls)->toBe(3);
        }
    }, $clock);
    $handler = lifecycleClockHandler(static function (object $command) use ($clock): mixed {
        expect($clock->calls)->toBe(3);
        return null;
    }, $clock);
    $bus = new CommandBus($handler, [$middleware], new OperationFactory($clock));

    $operation = $bus->dispatch(new stdClass());

    expect($clock->calls)->toBe(5)
        ->and($operation->createdAt)->toBe($times[0])
        ->and($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(CommandProcessEvent::class)
        ->and($events[0]->dispatchedAt)->toBe($times[1])
        ->and($events[0]->propagationStoppedAt)->toBe($times[2])
        ->and($events[0]->isPropagationStopped)->toBeTrue()
        ->and($operation->result)->not->toBeNull()
        ->and($operation->result->value)->toBeNull()
        ->and($operation->result->processedAt)->toBe($times[3])
        ->and($events[1])->toBeInstanceOf(CommandProcessedEvent::class)
        ->and($events[1]->dispatchedAt)->toBe($times[4])
        ->and($events[1]->operation)->toBe($operation);
});

it('uses UTC system time for event construction and stop when no clock is supplied', function (): void {
    $operation = Operation::create(new stdClass());
    $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $event = new CommandProcessEvent($operation);
    $event->stop();
    $firstStop = $event->propagationStoppedAt;
    $event->stop();
    $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    foreach ([$event->dispatchedAt, $firstStop] as $time) {
        expect($time)->toBeGreaterThanOrEqual($before)
            ->and($time)->toBeLessThanOrEqual($after)
            ->and($time->getTimezone()->getName())->toBe('UTC');
    }
    expect($event->propagationStoppedAt)->toBe($firstStop);
});

it('does not read completion time for a throwing command and timestamps the failure event', function (): void {
    $failure = new RuntimeException('handler failed');
    $processTime = new DateTimeImmutable('2025-02-03 10:00:00+09:00');
    $failedTime = new DateTimeImmutable('2025-02-03 10:00:01+09:00');
    $clock = new LifecycleSequenceClock([$processTime, $failedTime]);
    $events = [];
    $middleware = lifecycleClockMiddleware(static function ($event) use (&$events): void {
        $events[] = $event;
    }, $clock);
    $handler = lifecycleClockHandler(static fn (): never => throw $failure, $clock);
    $operation = Operation::create(new stdClass());

    try {
        $middleware->execute($operation, $handler);
        test()->fail('Expected handler failure.');
    } catch (RuntimeException $caught) {
        expect($caught)->toBe($failure);
    }

    expect($clock->calls)->toBe(2)
        ->and($events)->toHaveCount(2)
        ->and($events[0]->dispatchedAt)->toBe($processTime)
        ->and($events[1])->toBeInstanceOf(CommandFailedEvent::class)
        ->and($events[1]->exception)->toBe($failure)
        ->and($events[1]->dispatchedAt)->toBe($failedTime);
});

it('preserves both the command failure and the failure-event clock error', function (): void {
    $primary = new RuntimeException('handler failed');
    $secondary = new RuntimeException('event clock failed');
    $clock = new LifecycleSequenceClock([new DateTimeImmutable('2025-02-03T10:00:00Z'), $secondary]);
    $middleware = lifecycleClockMiddleware(static function (): void {}, $clock, true);
    $handler = lifecycleClockHandler(static fn (): never => throw $primary);

    try {
        $middleware->execute(Operation::create(new stdClass()), $handler);
        test()->fail('Expected aggregated failure.');
    } catch (CommandFailureNotificationException $caught) {
        expect($caught->commandFailure)->toBe($primary)
            ->and($caught->notificationFailure)->toBe($secondary);
    }
});

it('does not suppress a process-event clock error or run downstream', function (): void {
    $failure = new RuntimeException('process clock failed');
    $clock = new LifecycleSequenceClock([$failure]);
    $calls = 0;
    $middleware = lifecycleClockMiddleware(static function (): void {}, $clock, true);
    $handler = lifecycleClockHandler(static function () use (&$calls): void { ++$calls; });
    try {
        $middleware->execute(Operation::create(new stdClass()), $handler);
        test()->fail('Expected the clock error.');
    } catch (RuntimeException $caught) {
        expect($caught)->toBe($failure);
    }
    expect($calls)->toBe(0)->and($clock->calls)->toBe(1);
});

it('does not read time again after successful stop and leaves state unchanged on a failed stop', function (): void {
    $time = new DateTimeImmutable('2025-02-03T10:00:00Z');
    $failure = new RuntimeException('stop clock failed');
    $clock = new LifecycleSequenceClock([$time, $failure, $time]);
    $event = new CommandProcessEvent(Operation::create(new stdClass()), $clock);

    try {
        $event->stop();
        test()->fail('Expected stop clock failure.');
    } catch (RuntimeException $caught) {
        expect($caught)->toBe($failure);
    }
    expect($event->isPropagationStopped)->toBeFalse()
        ->and($event->propagationStoppedAt)->toBeNull();

    $event->stop();
    $event->stop();
    expect($clock->calls)->toBe(3)
        ->and($event->isPropagationStopped)->toBeTrue()
        ->and($event->propagationStoppedAt)->toBe($time);
});
