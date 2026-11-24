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
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\ConfigProvider;
use Componenta\CQRS\Query\QueryBusInterface;
use Componenta\DI\ContainerFactory;

final readonly class DiSixCqrsMessage
{
    public function __construct(public string $value) {}
}

final readonly class DiSixCqrsDependency
{
    public function __construct(public string $value) {}
}

final readonly class DiSixCqrsHandler
{
    public function __construct(private string $label) {}

    public function __invoke(DiSixCqrsMessage $message, DiSixCqrsDependency $dependency): string
    {
        return $this->label . ':' . $message->value . ':' . $dependency->value;
    }
}

final readonly class DiSixCqrsListener implements CommandListenerInterface
{
    public function __construct(private ArrayObject $events, private string $label) {}

    public function handleEvent(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): void {
        $this->events->append([$this->label, $event->operation->result?->value]);
    }
}

it('keeps container-owned handler listener and injected argument lifetimes across DI 6 mutations', function (): void {
    $events = new ArrayObject();
    $provider = new class ($events) extends BaseProvider {
        public function __construct(private readonly ArrayObject $events) {}

        protected function getConfig(): array
        {
            return [
                ConfigKey::COMMAND_HANDLERS => [
                    ['message' => DiSixCqrsMessage::class, 'service' => 'handler', 'method' => '__invoke'],
                ],
                ConfigKey::QUERY_HANDLERS => [
                    ['message' => DiSixCqrsMessage::class, 'service' => 'handler', 'method' => '__invoke'],
                ],
                ConfigKey::COMMAND_LISTENERS => [
                    ['message' => DiSixCqrsMessage::class, 'service' => 'listener', 'events' => [CommandProcessedEvent::class]],
                ],
                ConfigKey::COMMAND_MIDDLEWARES => [EventMiddleware::class],
            ];
        }

        protected function getServices(): array
        {
            return [
                'handler' => new DiSixCqrsHandler('first'),
                DiSixCqrsDependency::class => new DiSixCqrsDependency('dependency-one'),
                'listener' => new DiSixCqrsListener($this->events, 'first'),
            ];
        }
    };
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(), $provider);
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $commands = $container->get(CommandBusInterface::class);
    $queries = $container->get(QueryBusInterface::class);

    expect($commands->dispatch(new DiSixCqrsMessage('a'))->result?->value)->toBe('first:a:dependency-one')
        ->and($queries->handle(new DiSixCqrsMessage('b')))->toBe('first:b:dependency-one');

    $container->set('handler', new DiSixCqrsHandler('second'));
    $container->set(DiSixCqrsDependency::class, new DiSixCqrsDependency('dependency-two'));
    $container->set('listener', new DiSixCqrsListener($events, 'second'));

    expect($commands->dispatch(new DiSixCqrsMessage('c'))->result?->value)->toBe('second:c:dependency-two')
        ->and($queries->handle(new DiSixCqrsMessage('d')))->toBe('second:d:dependency-two')
        ->and($events->getArrayCopy())->toBe([
            ['first', 'first:a:dependency-one'],
            ['second', 'second:c:dependency-two'],
        ]);
});
