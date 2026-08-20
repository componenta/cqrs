# Componenta CQRS

`componenta/cqrs` — нейтральный CQRS runtime для PHP 8.4+. Пакет предоставляет command/query bus, immutable command operations, middleware contracts, локаторы handlers/listeners, lifecycle events команд, metadata access и версионированную CQRS-карту.

Policy, retry, locking, transactions, async transport, workers и application discovery вынесены в отдельные пакеты.

## Установка

```bash
composer require componenta/cqrs
```

## Runtime services

Зарегистрируйте `Componenta\CQRS\ConfigProvider`. Он предоставляет стандартные command/query bus, locators, operation factory, metadata provider и CQRS map provider.

Основные ключи конфигурации:

```php
Componenta\CQRS\ConfigKey::COMMAND_MIDDLEWARES;
Componenta\CQRS\ConfigKey::QUERY_MIDDLEWARES;
Componenta\CQRS\ConfigKey::CQRS_MAP;
Componenta\CQRS\ConfigKey::COMMAND_METADATA_ATTRIBUTES;
```

Middleware выполняются в порядке, указанном в конфигурации. Отсутствующий middleware key означает пустую цепочку.

## Команды

Один вызов `CommandBusInterface::dispatch()` создаёт одну `OperationInterface` и проводит её через полный command pipeline:

```php
$operation = $commands->dispatch(new CreateUserCommand($email));
$result = $operation->result?->value;
```

Operation содержит UUID v7, command instance, timestamp, attributes и `OperationResult` после синхронного завершения.

### Несколько команд

`BatchCommandBus` декорирует `CommandBusInterface` и добавляет явный последовательный multi-dispatch:

```php
$batch = new Componenta\CQRS\Command\BatchCommandBus($commands);

$operations = $batch->dispatchMany([
    new FirstCommand(),
    new SecondCommand(),
]);
```

Каждая команда независимо проходит через wrapped bus и получает собственную operation. `dispatchMany()` предварительно буферизует и валидирует iterable, затем сохраняет исходный порядок команд.

Nested-вызовы `CommandBusInterface::dispatch()` имеют обычную reentrant-семантику. Core больше не содержит `SequentialMiddleware` и не откладывает nested commands скрытой очередью. Если действие должно произойти после завершения текущей команды или транзакции, используйте явную модель: events, outbox, async transport или workflow/process manager.

## Command middleware

Command middleware реализует:

```php
public function execute(
    OperationInterface $operation,
    OperationHandlerInterface $handler,
): OperationInterface;
```

В core находится `EventMiddleware`; `HandleCommandHandler` является terminal operation handler и подключается bus factory отдельно.

Порядок middleware является частью поведения. В частности, если одновременно используются retry и transaction middleware, retry должен оборачивать transaction middleware, чтобы каждая попытка имела собственную transaction boundary.

## Command lifecycle events

`EventMiddleware` может публиковать:

- `CommandProcessEvent` до выполнения handler;
- `CommandProcessedEvent` после успешного выполнения;
- `CommandFailedEvent` после ошибки перед повторным выбросом exception.

Ошибки listeners по умолчанию распространяются. Явно созданный `EventMiddleware` может подавлять только ошибки тела listener; ошибки locator, map и DI продолжают распространяться.

## Queries

```php
$result = $queries->handle(new GetUserQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` нормализует array context в immutable `Context` до вызова query middleware.

## CQRS map v2

Runtime discovery и compiled execution используют одну версионированную модель карты. Пустой валидный артефакт имеет вид:

```php
['version' => 2]
```

Map содержит command handlers/listeners/known commands/metadata и query handlers. Сериализация детерминирована; конфликтующие handlers или metadata завершаются ошибкой вместо неявного перезаписывания.

### Поведение окружений

Только `APP_ENV=development` может работать с пустой базовой картой и live discovery из `componenta/cqrs-app`.

Любое другое значение окружения — включая `production`, `staging` и `test` — является compiled-only и требует актуальный v2-артефакт `ConfigKey::CQRS_MAP`. Отсутствующий, legacy или неподдерживаемый артефакт приводит к fail-fast ошибке.

Production map собирается из development discovery:

```bash
APP_ENV=development php bin/console.php app:build
```

## Metadata

`CommandMetadataProviderInterface` предоставляет metadata команд:

```php
$metadata->get($command, Attribute::class);
$metadata->isKnown($command);
```

Стандартный compiled provider читает зарегистрированные attributes из активной CQRS map. Reflection fallback используется только для команд, неизвестных карте. Дополнительные пакеты регистрируют свои metadata attributes через `ConfigKey::COMMAND_METADATA_ATTRIBUTES`, чтобы `componenta/cqrs-app` обнаруживал и компилировал их.

## Discovery attributes

`componenta/cqrs-app` понимает:

```php
#[Componenta\CQRS\Command\Attribute\AsCommandHandler]
#[Componenta\CQRS\Command\Attribute\AsCommandListener]
#[Componenta\CQRS\Query\Attribute\AsQueryHandler]
```

Message обязан находиться в первом parameter slot handler. Дополнительные обязательные параметры handler не dependency-inject-ятся CQRS runtime.

## Дополнительные пакеты

| Пакет | Назначение |
|---|---|
| `componenta/cqrs-app` | Development discovery и build-time compilation CQRS map |
| `componenta/cqrs-policy` | Авторизация command/query |
| `componenta/cqrs-retry` | Retry metadata и middleware |
| `componenta/cqrs-lock` | Resource-lock metadata и middleware |
| `componenta/cqrs-transaction-cycle` | Cycle Database transaction middleware |
| `componenta/cqrs-transport` | Async transport contracts, serializers, middleware и worker |
| `componenta/cqrs-transport-cycle` | Cycle Database transport |
| `componenta/cqrs-transport-console` | Symfony Console worker |

## Extension points

- `CommandBusInterface` / `QueryBusInterface` — альтернативные bus/decorators;
- `OperationFactoryInterface` — custom operation construction;
- command/query `MiddlewareInterface` — cross-cutting execution behavior;
- `CommandNameResolverInterface` / `QueryNameResolverInterface` — custom message names;
- locator interfaces — альтернативное разрешение handlers/listeners;
- `CommandMetadataProviderInterface` — альтернативный источник metadata;
- `CqrsMapProviderInterface` — альтернативный immutable map source.

## Проверка

```bash
composer test
composer analyse
```

CI проверяет PHP 8.4/8.5, test suite и PHPStan на максимальном уровне.
