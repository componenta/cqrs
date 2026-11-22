<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;
use Componenta\CQRS\Internal\RegistrationNormalizer;
use Componenta\CQRS\Map\Exception\CqrsMapConflictException;

it('rejects conflicting methods on the same service for commands and queries', function (): void {
    $first = ['message' => 'message', 'service' => 'same.service', 'method' => 'handleA'];
    $second = ['message' => 'message', 'service' => 'same.service', 'method' => 'handleB'];

    expect(fn () => RegistrationNormalizer::registrations([$first, $second]))
        ->toThrow(CqrsMapConflictException::class)
        ->and(fn () => RegistrationNormalizer::registrations(queries: [$first, $second]))
        ->toThrow(CqrsMapConflictException::class);
});

it('rethrows the identical handler failure after failed-event notification', function (): void {
    $primary = new RuntimeException('failed in command handler');
    $events = new ArrayObject();
    $listener = new readonly class ($events) implements CommandListenerInterface {
        public function __construct(private ArrayObject $events) {}
        public function handleEvent(
            CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
        ): void {
            $this->events->append($event::class);
        }
    };
    $locator = new readonly class ($listener) implements CommandListenersLocatorInterface {
        public function __construct(private CommandListenerInterface $listener) {}
        public function locateFor(
            CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
        ): iterable {
            yield $this->listener;
        }
    };
    $handler = new readonly class ($primary) implements OperationHandlerInterface {
        public function __construct(private Throwable $error) {}
        public function handle(OperationInterface $operation): OperationInterface
        {
            throw $this->error;
        }
    };

    try {
        (new EventMiddleware($locator))->execute(Operation::create(new stdClass()), $handler);
        test()->fail('Expected handler failure.');
    } catch (RuntimeException $error) {
        expect($error)->toBe($primary)
            ->and($events->getArrayCopy())->toBe([
                CommandProcessEvent::class,
                CommandFailedEvent::class,
            ]);
    }
});

it('stops resolving later process listeners when propagation is stopped', function (): void {
    $calls = new ArrayObject();
    $first = new readonly class implements CommandListenerInterface {
        public function handleEvent(
            CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
        ): void {
            if ($event instanceof CommandProcessEvent) {
                $event->stop();
            }
        }
    };
    $second = new readonly class ($calls) implements CommandListenerInterface {
        public function __construct(private ArrayObject $calls) {}
        public function handleEvent(
            CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
        ): void {
            $this->calls->append($event::class);
        }
    };
    $locator = new readonly class ($first, $second) implements CommandListenersLocatorInterface {
        public function __construct(
            private CommandListenerInterface $first,
            private CommandListenerInterface $second,
        ) {}
        public function locateFor(
            CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
        ): iterable {
            yield $this->first;
            yield $this->second;
        }
    };
    $handler = new readonly class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation->withResult(new OperationResult('ok'));
        }
    };

    $result = (new EventMiddleware($locator))->execute(Operation::create(new stdClass()), $handler);

    expect($result->result?->value)->toBe('ok')
        ->and($calls->getArrayCopy())->toBe([CommandProcessedEvent::class]);
});
