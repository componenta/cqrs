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
use Componenta\CQRS\Command\Metadata\CommandMetadataProviderInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\ConfigProvider;
use Componenta\CQRS\Query\QueryBusInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Identity\RfcUuidInterface;
use Componenta\Identity\Uuid;
use Psr\Clock\ClockInterface;

require $argv[1] ?? throw new InvalidArgumentException('Consumer autoloader path required.');

#[Attribute(Attribute::TARGET_CLASS)]
final class ConsumerMetadata
{
    public function __construct(public object $value) {}
}

#[ConsumerMetadata(new stdClass())]
final readonly class ConsumerMessage
{
    public function __construct(public int $value) {}
}

final readonly class ConsumerDependency
{
    public function __construct(public int $offset) {}
}

final readonly class ConsumerHandler
{
    public function __invoke(ConsumerMessage $message, ConsumerDependency $dependency): int
    {
        return $message->value + $dependency->offset;
    }
}

final class ConsumerListener implements CommandListenerInterface
{
    public array $results = [];

    public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
    {
        $this->results[] = $event->operation->result?->value;
    }
}

$listener = new ConsumerListener();
$clock = new class implements ClockInterface {
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2025-02-03T10:00:00.123456+09:00'); }
};
$provider = new class ($listener, $clock) extends BaseProvider {
    public function __construct(private readonly ConsumerListener $listener, private readonly ClockInterface $clock) {}

    protected function getConfig(): array
    {
        return [
            ConfigKey::COMMAND_HANDLERS => [
                ['message' => ConsumerMessage::class, 'service' => ConsumerHandler::class, 'method' => '__invoke'],
            ],
            ConfigKey::QUERY_HANDLERS => [
                ['message' => ConsumerMessage::class, 'service' => ConsumerHandler::class, 'method' => '__invoke'],
            ],
            ConfigKey::COMMAND_LISTENERS => [
                ['message' => ConsumerMessage::class, 'service' => ConsumerListener::class, 'events' => [CommandProcessedEvent::class]],
            ],
            ConfigKey::COMMAND_MIDDLEWARES => [EventMiddleware::class],
        ];
    }

    protected function getServices(): array
    {
        return [
            ConsumerHandler::class => new ConsumerHandler(),
            ConsumerDependency::class => new ConsumerDependency(1),
            ConsumerListener::class => $this->listener,
            ClockInterface::class => $this->clock,
        ];
    }
};

$composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(), $provider);
$container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
$commands = $container->get(CommandBusInterface::class);
$queries = $container->get(QueryBusInterface::class);
$metadata = $container->get(CommandMetadataProviderInterface::class);
$message = new ConsumerMessage(41);
$value = 'original';
$operation = $commands->dispatch($message, ['trace' => &$value]);
$value = 'changed';

if (!$operation->id instanceof Uuid
    || !$operation->id instanceof RfcUuidInterface
    || $operation->id->version !== 7
    || $operation->createdAt->format('Y-m-d H:i:s.uP') !== '2025-02-03 10:00:00.123456+09:00'
    || $operation->result?->processedAt->format('Y-m-d H:i:s.uP') !== '2025-02-03 10:00:00.123456+09:00'
    || $operation->result?->value !== 42
    || $operation->command !== $message
    || $operation->attributes !== ['trace' => 'original']
    || $queries->handle($message) !== 42
    || $listener->results !== [42]) {
    throw new RuntimeException('Consumer bus, attribute or listener contract failed.');
}

$first = $metadata->get($message, ConsumerMetadata::class);
$second = $metadata->get($message, ConsumerMetadata::class);
if (!$first instanceof ConsumerMetadata || !$second instanceof ConsumerMetadata
    || $first === $second || $first->value === $second->value) {
    throw new RuntimeException('Consumer metadata freshness contract failed.');
}

$container->set(ConsumerDependency::class, new ConsumerDependency(2));
if ($commands->dispatch($message)->result?->value !== 43
    || $queries->handle($message) !== 43
    || $listener->results !== [42, 43]) {
    throw new RuntimeException('Consumer live DI dependency contract failed.');
}

echo 'CQRS production consumer contracts passed on PHP ' . PHP_VERSION . PHP_EOL;
