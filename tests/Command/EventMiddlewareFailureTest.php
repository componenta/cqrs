<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Exception\CommandFailureNotificationException;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;

final readonly class EventPhaseTestLocator implements CommandListenersLocatorInterface
{
    public function __construct(private CommandListenerInterface $listener)
    {
    }

    public function locateFor(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): iterable {
        yield $this->listener;
    }
}

final class EventPhaseTestListener implements CommandListenerInterface
{
    /** @var list<class-string> */
    public array $events = [];

    public function __construct(private readonly Closure $callback)
    {
    }

    public function handleEvent(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): void {
        $this->events[] = $event::class;
        ($this->callback)($event);
    }
}

it('does not emit a command-failed event for a post-processing listener failure', function (): void {
    $listener = new EventPhaseTestListener(
        static function (object $event): void {
            if ($event instanceof CommandProcessedEvent) {
                throw new RuntimeException('processed listener failed');
            }
        },
    );
    $middleware = new EventMiddleware(
        new EventPhaseTestLocator($listener),
        suppressExceptions: false,
    );
    $handler = new class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation->withResult(new OperationResult('done'));
        }
    };

    expect(fn() => $middleware->execute(Operation::create(new stdClass()), $handler))
        ->toThrow(RuntimeException::class, 'processed listener failed')
        ->and($listener->events)->toBe([
            CommandProcessEvent::class,
            CommandProcessedEvent::class,
        ]);
});

it('preserves the handler failure when command-failed notification also fails', function (): void {
    $primary = new RuntimeException('handler failed');
    $notification = new RuntimeException('failure listener failed');
    $listener = new EventPhaseTestListener(
        static function (object $event) use ($notification): void {
            if ($event instanceof CommandFailedEvent) {
                throw $notification;
            }
        },
    );
    $middleware = new EventMiddleware(
        new EventPhaseTestLocator($listener),
        suppressExceptions: false,
    );
    $handler = new class ($primary) implements OperationHandlerInterface {
        public function __construct(private readonly Throwable $failure)
        {
        }

        public function handle(OperationInterface $operation): OperationInterface
        {
            throw $this->failure;
        }
    };

    try {
        $middleware->execute(Operation::create(new stdClass()), $handler);
        test()->fail('Expected aggregate listener failure.');
    } catch (CommandFailureNotificationException $exception) {
        expect($exception->commandFailure)->toBe($primary)
            ->and($exception->notificationFailure)->toBe($notification)
            ->and($exception->getPrevious())->toBe($primary);
    }
});

it('suppresses only listener-body failures when explicitly requested', function (): void {
    $listener = new EventPhaseTestListener(
        static fn(): never => throw new RuntimeException('listener failed'),
    );
    $middleware = new EventMiddleware(
        new EventPhaseTestLocator($listener),
        suppressExceptions: true,
    );
    $handler = new class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation->withResult(new OperationResult('done'));
        }
    };

    $result = $middleware->execute(Operation::create(new stdClass()), $handler);

    expect($result->result?->value)->toBe('done')
        ->and($listener->events)->toBe([
            CommandProcessEvent::class,
            CommandProcessedEvent::class,
        ]);
});

it('never suppresses locator and configuration failures', function (): void {
    $locator = new class implements CommandListenersLocatorInterface {
        public function locateFor(
            CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
        ): iterable {
            throw new RuntimeException('locator failed');
            yield;
        }
    };
    $middleware = new EventMiddleware(
        $locator,
        suppressExceptions: true,
    );
    $handler = new class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation;
        }
    };

    expect(fn() => $middleware->execute(Operation::create(new stdClass()), $handler))
        ->toThrow(RuntimeException::class, 'locator failed');
});
