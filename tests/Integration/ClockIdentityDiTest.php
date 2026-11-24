<?php

declare(strict_types=1);

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigProvider as BaseProvider;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\OperationFactory;
use Componenta\CQRS\Command\OperationFactoryInterface;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\ConfigProvider;
use Componenta\DI\ContainerFactory;
use Componenta\Identity\UuidFactory;
use Componenta\Identity\UuidFactoryInterface;
use Componenta\Identity\UuidGenerationVersion;
use Psr\Clock\ClockInterface;

final class IntegratedCqrsClock implements ClockInterface
{
    public int $calls = 0;
    public function __construct(public DateTimeImmutable $time) {}
    public function now(): DateTimeImmutable { ++$this->calls; return $this->time; }
}

final class IntegratedCqrsListener implements CommandListenerInterface
{
    public array $events = [];
    public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
    {
        $this->events[] = $event;
        if ($event instanceof CommandProcessEvent) {
            $event->stop();
        }
    }
}

it('injects configured PSR-20 clock and Identity generator into the real DI composition', function (): void {
    $time = new DateTimeImmutable('2025-02-03 10:00:00.123456', new DateTimeZone('Asia/Tokyo'));
    $clock = new IntegratedCqrsClock($time);
    $generator = new UuidFactory(UuidGenerationVersion::V4);
    $listener = new IntegratedCqrsListener();
    $provider = new class ($clock, $generator, $listener) extends BaseProvider {
        public function __construct(
            private IntegratedCqrsClock $clock,
            private UuidFactoryInterface $generator,
            private IntegratedCqrsListener $listener,
        ) {}
        protected function getConfig(): array
        {
            return [
                ConfigKey::COMMAND_HANDLERS => [
                    ['message' => stdClass::class, 'service' => 'clock.handler', 'method' => '__invoke'],
                ],
                ConfigKey::COMMAND_LISTENERS => [
                    ['message' => stdClass::class, 'service' => 'clock.listener', 'events' => [CommandProcessEvent::class, CommandProcessedEvent::class]],
                ],
                ConfigKey::COMMAND_MIDDLEWARES => [EventMiddleware::class],
            ];
        }
        protected function getServices(): array
        {
            return [
                ClockInterface::class => $this->clock,
                UuidFactoryInterface::class => $this->generator,
                'clock.handler' => static fn (stdClass $message): int => 42,
                'clock.listener' => $this->listener,
            ];
        }
    };
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(), $provider);
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    expect($container->get(OperationFactoryInterface::class))->toBe($container->get(OperationFactory::class));
    $bus = $container->get(CommandBusInterface::class);
    $first = $bus->dispatch(new stdClass());

    expect($first->createdAt)->toBe($time)
        ->and($first->id->version)->toBe(4)
        ->and($first->result?->value)->toBe(42)
        ->and($first->result?->processedAt)->toBe($time)
        ->and($listener->events[0]->dispatchedAt)->toBe($time)
        ->and($listener->events[0]->propagationStoppedAt)->toBe($time)
        ->and($listener->events[1]->dispatchedAt)->toBe($time)
        ->and($clock->calls)->toBe(5);

    $next = $clock->time = new DateTimeImmutable('2025-02-02T01:00:00-05:00');
    $second = $bus->dispatch(new stdClass());

    expect($second->createdAt)->toBe($next)
        ->and($second->result?->processedAt)->toBe($next)
        ->and($listener->events[2]->propagationStoppedAt)->toBe($next)
        ->and($second->id->equals($first->id))->toBeFalse()
        ->and($clock->calls)->toBe(10);
});
