# Componenta CQRS

`componenta/cqrs` — базовый CQRS runtime для PHP 8.4+. Пакет содержит command/query bus, локаторы обработчиков, объект операции команды, события команд и атрибуты discovery, которые использует application-level tooling.

В core остаются только нейтральные части runtime. Middleware, которым нужны policy, locks, очереди, транзакции базы данных или console integration, вынесены в отдельные пакеты.

## Установка

```bash
composer require componenta/cqrs
```

## Зависимости

| Зависимость | Назначение |
|---|---|
| PHP `>=8.4` | Современные возможности языка и strict types. |
| `componenta/arrayable` | Общий контракт преобразования в массив. |
| `componenta/config` | Интеграция с config provider. |
| `componenta/di` | Вызов handlers/listeners через `CallableInvokerInterface`. |
| `componenta/iterator` | Iterator helpers для локаторов. |
| `componenta/reflection` | Metadata helpers для command attributes. |
| `psr/container` | Получение сервисов. |
| `ramsey/uuid` | Идентификаторы операций. |

## Дополнительные пакеты

| Пакет | Что добавляет |
|---|---|
| `componenta/cqrs-policy` | Policy middleware для команд и запросов. Для публичных сценариев используйте `#[Allow]` из `componenta/policy`; skip-policy флаги не должны быть моделью публичного доступа. |
| `componenta/cqrs-retry` | Retry middleware для команд с `#[Retry]`. |
| `componenta/cqrs-lock` | Symfony Lock middleware для команд с `#[Lock]`. |
| `componenta/cqrs-transaction-cycle` | Transaction middleware для Cycle Database. |
| `componenta/cqrs-transport` | Transport contracts, serializer, async middleware и command worker. |
| `componenta/cqrs-transport-cycle` | Реализация транспорта на Cycle Database. |
| `componenta/cqrs-transport-console` | Symfony Console команда воркера. |
| `componenta/cqrs-app` | Attribute discovery и build-time map compilers. |

## Быстрый старт

```php
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\ConfigKey;

final readonly class CalculateTotalCommand
{
    public function __construct(
        public int $price,
        public int $quantity,
    ) {}
}

final readonly class CalculateTotalHandler
{
    public function __invoke(CalculateTotalCommand $command): int
    {
        return $command->price * $command->quantity;
    }
}

return [
    new Componenta\CQRS\ConfigProvider(),

    ConfigKey::COMMAND_HANDLER_MAP => [
        CalculateTotalCommand::class => [CalculateTotalHandler::class, '__invoke'],
    ],
];

/** @var CommandBusInterface $commands */
$commands = $container->get(CommandBusInterface::class);
$operation = $commands->dispatch(new CalculateTotalCommand(price: 50, quantity: 3));

$result = $operation->result?->value; // 150
```

## Конфигурация

Зарегистрируйте runtime provider:

```php
return [
    new Componenta\CQRS\ConfigProvider(),
];
```

Provider регистрирует:

| Сервис | Назначение |
|---|---|
| `CommandBusInterface` | Выполняет команды через настроенную command middleware chain. |
| `QueryBusInterface` | Выполняет запросы через настроенную query middleware chain. |
| `CommandHandlerLocatorInterface` | Читает `ConfigKey::COMMAND_HANDLER_MAP`. |
| `CommandListenersLocatorInterface` | Читает `ConfigKey::COMMAND_LISTENER_MAP`. |
| `QueryHandlerLocatorInterface` | Читает `ConfigKey::QUERY_HANDLER_MAP`. |
| `CommandAttributeProviderInterface` | Читает `#[Async]`, `#[Retry]` и `#[Lock]` из compiled maps или reflection. |

Основные ключи:

| Ключ | Значение |
|---|---|
| `ConfigKey::COMMAND_MIDDLEWARES` | Список command middleware в порядке выполнения. |
| `ConfigKey::QUERY_MIDDLEWARES` | Список query middleware в порядке выполнения. |
| `ConfigKey::COMMAND_HANDLER_MAP` | Карта `Command::class => callable|[Handler::class, method]`. |
| `ConfigKey::COMMAND_LISTENER_MAP` | Карта command event listeners. |
| `ConfigKey::QUERY_HANDLER_MAP` | Карта `Query::class => callable|[Handler::class, method]`. |
| `ConfigKey::COMMAND_ATTRIBUTE_MAP` | Compiled metadata для `#[Async]`, `#[Retry]` и `#[Lock]`. |
| `ConfigKey::COMPILED_MAPS` | Включает compiled maps. |

`Componenta\CQRS\ConfigProvider` по умолчанию регистрирует пустые command/query middleware lists. Middleware из optional packages добавляется приложением, когда нужны authorization, retries, locks, transactions, async execution или workers.

## Команды

Команда — объект с данными для сценария, который меняет состояние. `CommandBusInterface::dispatch(object $command, array $attributes = [])` возвращает immutable `OperationInterface` с id операции, attributes и result, если команда выполнена синхронно.

Handlers ищутся через `CommandHandlerLocatorInterface`. Запись handler map может быть callable или парой `[class-string, method-string]`. Пары class/method лениво резолвятся через контейнер.

`#[AsCommandHandler(?string $command = null)]` — discovery metadata для `componenta/cqrs-app`. Если command не указан, `cqrs-app` выводит его из параметра handler.

## Command Middleware

Command middleware реализует:

```php
public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface;
```

Core middleware:

| Middleware | Ответственность |
|---|---|
| `SequentialMiddleware` | Выполняет nested dispatch после root command. |
| `EventMiddleware` | Публикует command lifecycle events. |
| `HandleCommandHandler` | Terminal handler, который находит и вызывает command handler. Bus factory подключает его отдельно. |

Порядок middleware является частью поведения. Cross-cutting concerns лучше держать в middleware, а не прятать в handlers.

## Атрибуты команд

| Атрибут | Target | Constructor | Поведение |
|---|---|---|---|
| `#[AsCommandHandler]` | class или method | `?string $command = null` | Discovery metadata для command handlers. |
| `#[AsCommandListener]` | class | `string $command, int $priority = 0, array $eventTypes = []` | Discovery metadata для command event listeners. |
| `#[Async]` | command class | `string $transport = 'default', int $delay = 0` | Metadata для `componenta/cqrs-transport`. |
| `#[Retry]` | command class | `int $attempts = 3, int $delayMs = 100, float $multiplier = 1.0, int $maxDelayMs = 10000` | Metadata для `componenta/cqrs-retry`. |
| `#[Lock]` | command class | `string $key, float $ttl = 300.0, bool $blocking = true` | Metadata для `componenta/cqrs-lock`. |


## События команд

| Event | Когда |
|---|---|
| `CommandProcessEvent` | До выполнения команды. |
| `CommandProcessedEvent` | После успешного выполнения. |
| `CommandFailedEvent` | После ошибки, перед повторным выбросом exception. |

Listeners ищутся через `CommandListenersLocatorInterface`. `EventMiddleware` по умолчанию подавляет ошибки listeners. Для fail-fast поведения в development используйте `suppressExceptions: false`.

## Запросы

Запросы описывают read use cases и сразу возвращают результат handler:

```php
use Componenta\CQRS\Query\QueryBusInterface;

$post = $queries->handle(new GetPostQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` преобразует массивы в immutable `Context` до передачи в middleware.

Handlers ищутся через `QueryHandlerLocatorInterface`. `#[AsQueryHandler(?string $query = null)]` — discovery metadata для `componenta/cqrs-app`.

## Extension Points

| Что заменить | Contract | Когда использовать |
|---|---|---|
| Command name | `NamedCommandInterface` или `CommandNameResolverInterface` | Ключ handler map должен отличаться от FQCN команды. |
| Query name | `NamedQueryInterface` или `QueryNameResolverInterface` | Ключ handler map должен отличаться от FQCN запроса. |
| Command chain step | `Componenta\CQRS\Command\Middleware\MiddlewareInterface` | Нужен custom step вокруг command execution. |
| Query chain step | `Componenta\CQRS\Query\Middleware\MiddlewareInterface` | Нужен custom step вокруг query execution. |
| Command event listeners | `CommandListenerInterface` | Side effect должен реагировать на command events без изменения command handler. |
| Command metadata | `CommandAttributeProviderInterface` | `#[Async]`, `#[Retry]` и `#[Lock]` должны приходить не из reflection или стандартной compiled map. |

## Ошибки

| Ошибка | Exception |
|---|---|
| Command handler не найден | `Componenta\CQRS\Command\Exception\HandlerNotFoundException` |
| Некорректный command handler | `Componenta\CQRS\Command\Exception\InvalidHandlerException` |
| Query handler не найден | `Componenta\CQRS\Query\Exception\HandlerNotFoundException` |

