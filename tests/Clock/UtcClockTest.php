<?php

declare(strict_types=1);

use Componenta\CQRS\Clock\UtcClock;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationFactory;
use Componenta\CQRS\Command\OperationResult;
use Psr\Clock\ClockInterface;

it('returns fresh immutable UTC time independent of the process timezone', function (): void {
    $timezone = date_default_timezone_get();
    try {
        date_default_timezone_set('Pacific/Auckland');
        $before = new DateTimeImmutable();
        $clock = new UtcClock();
        $first = $clock->now();
        $second = $clock->now();
        $after = new DateTimeImmutable();

        expect($clock)->toBeInstanceOf(ClockInterface::class)
            ->and($first)->not->toBe($second);
        foreach ([$first, $second] as $time) {
            expect($time)->toBeInstanceOf(DateTimeImmutable::class)
                ->and($time->getTimezone()->getName())->toBe('UTC')
                ->and($time->getOffset())->toBe(0)
                ->and($time)->toBeGreaterThanOrEqual($before)
                ->and($time)->toBeLessThanOrEqual($after);
        }
        expect(date_default_timezone_get())->toBe('Pacific/Auckland');
    } finally {
        date_default_timezone_set($timezone);
    }
});

it('uses UTC at every default creation boundary in a non-UTC process', function (): void {
    $timezone = date_default_timezone_get();
    try {
        date_default_timezone_set('Asia/Tokyo');
        $locator = new class implements CommandHandlerLocatorInterface {
            public function locateFor(object $command): callable
            {
                return static fn (object $command): string => 'done';
            }
        };
        $before = new DateTimeImmutable();
        $operation = (new OperationFactory())->create(new stdClass());
        $standalone = Operation::create(new stdClass());
        $completed = (new HandleCommandHandler($locator))->handle($operation);
        $result = new OperationResult('done');
        $process = new CommandProcessEvent($operation);
        $processed = new CommandProcessedEvent($completed);
        $failed = new CommandFailedEvent($operation, new RuntimeException('failure'));
        $process->stop();
        $after = new DateTimeImmutable();

        foreach ([
            $operation->createdAt, $standalone->createdAt,
            $completed->result->processedAt, $result->processedAt,
            $process->dispatchedAt, $processed->dispatchedAt,
            $failed->dispatchedAt, $process->propagationStoppedAt,
        ] as $time) {
            expect($time)->toBeInstanceOf(DateTimeImmutable::class)
                ->and($time->getTimezone()->getName())->toBe('UTC')
                ->and($time)->toBeGreaterThanOrEqual($before)
                ->and($time)->toBeLessThanOrEqual($after);
        }
    } finally {
        date_default_timezone_set($timezone);
    }
});

it('rejects an explicit null clock instead of accepting a missing dependency', function (): void {
    $operation = Operation::create(new stdClass());
    $handlerLocator = new class implements CommandHandlerLocatorInterface {
        public function locateFor(object $command): callable
        {
            return static fn (): null => null;
        }
    };
    $listenerLocator = new class implements CommandListenersLocatorInterface {
        public function locateFor(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): iterable
        {
            return [];
        }
    };
    expect(fn () => new OperationFactory(clock: null))->toThrow(TypeError::class)
        ->and(fn () => new HandleCommandHandler($handlerLocator, clock: null))->toThrow(TypeError::class)
        ->and(fn () => new EventMiddleware($listenerLocator, clock: null))->toThrow(TypeError::class)
        ->and(fn () => new CommandProcessEvent($operation, clock: null))->toThrow(TypeError::class)
        ->and(fn () => new CommandProcessedEvent($operation, clock: null))->toThrow(TypeError::class)
        ->and(fn () => new CommandFailedEvent($operation, new RuntimeException(), clock: null))->toThrow(TypeError::class);
});
