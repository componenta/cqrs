# Componenta CQRS

`componenta/cqrs` — нейтральный CQRS runtime для PHP 8.4+. `main` — линия CQRS v4.

```bash
composer require componenta/cqrs
```

Зарегистрируйте `Componenta\CQRS\ConfigProvider`. Он предоставляет стандартные command/query bus, locators, operation factory, metadata provider и CQRS map provider.

## Команды и операции

```php
$operation = $commands->dispatch(new CreateUserCommand($email));
$result = $operation->result?->value;
```

Один `dispatch()` создаёт одну `OperationInterface` и проводит её через полный command pipeline.

Operation содержит:

- UUID v7 `id`;
- экземпляр `command`;
- `createdAt` — UTC-время создания локального объекта operation для dispatch;
- `attributes` со строгим контрактом `array<string,mixed>`;
- необязательный `OperationResult`, где `processedAt` фиксирует синхронное завершение.

Поле называется именно `createdAt`, а не `startedAt`: operation создаётся до middleware и это не является моментом начала handler. Async transport не должен восстанавливать producer `createdAt` как время начала выполнения worker.

В CQRS v4 operation factory отделён от middleware composition:

```php
$bus = new Componenta\CQRS\Command\CommandBus(
    commandHandler: $terminalHandler,
    middlewares: [
        $firstMiddleware,
        $secondMiddleware,
    ],
    operationFactory: $operationFactory,
);
```

Если factory не передан, используется `OperationFactory`.

### Несколько команд

`BatchCommandBus` — явный декоратор последовательного multi-dispatch:

```php
$batch = new Componenta\CQRS\Command\BatchCommandBus($commands);

$operations = $batch->dispatchMany([
    new FirstCommand(),
    new SecondCommand(),
]);
```

Каждая команда независимо проходит wrapped bus и получает собственную operation. `SequentialMiddleware` удалён; nested `dispatch()` имеет обычную reentrant-семантику. Если действие должно произойти после текущей команды или транзакции, используйте явный event, outbox, async transport или workflow/process manager.

## Command middleware

Command middleware реализует:

```php
public function execute(
    OperationInterface $operation,
    OperationHandlerInterface $handler,
): OperationInterface;
```

`HandleCommandHandler` является terminal handler. `ConfigProvider` регистрирует `EventMiddleware` как сервис, но не добавляет его в pipeline автоматически. Добавьте его в `ConfigKey::COMMAND_MIDDLEWARES`, если должны выполняться command lifecycle listeners.

### Порядок middleware

Middleware выполняются ровно в том порядке, который задаёт приложение. `CommandBus` валидирует список middleware и компилирует его без автоматической перестановки и без знания о семантике optional packages.

Поэтому порядок является частью конфигурации приложения. Например:

```text
RetryMiddleware
  TransactionMiddleware
    handler
```

создаёт отдельную transaction для каждой retry-попытки, а:

```text
TransactionMiddleware
  RetryMiddleware
    handler
```

оставляет все retry-попытки внутри одной внешней transaction. CQRS core не запрещает ни один вариант: приложение выбирает нужную семантику.

README optional-пакетов описывают рекомендуемые композиции и последствия разных порядков, но ответственность за итоговую topology остаётся у приложения.

## Command lifecycle events

`EventMiddleware` может публиковать:

- `CommandProcessEvent` до downstream execution;
- `CommandProcessedEvent` после успеха;
- `CommandFailedEvent` после ошибки перед повторным выбросом exception.

Ошибки listeners по умолчанию распространяются. Положение `EventMiddleware` относительно policy, transport, retry, lock, transaction и пользовательских middleware определяется конфигурацией приложения.

## Queries

```php
$result = $queries->handle(new GetUserQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` нормализует array context в immutable `Context`. Ключи query context подчиняются тому же non-empty string-key контракту, что и operation attributes.

## CQRS map и окружения

Runtime discovery и compiled execution используют одну versioned CQRS map. Пустой валидный артефакт:

```php
['version' => 2]
```

Если `APP_ENV` отсутствует, окружение по умолчанию считается development. Поэтому отсутствующий `APP_ENV` или явный `APP_ENV=development` может использовать live discovery overlay из `componenta/cqrs-app`. Любое явно заданное non-development окружение, включая `production`, `staging` и `test`, является compiled-only и требует актуальный map artifact.

Сборка:

```bash
APP_ENV=development php bin/console.php app:build
```

## Metadata

`CommandMetadataProviderInterface` предоставляет:

```php
$metadata->get($command, Attribute::class);
$metadata->isKnown($command);
```

Стандартный `CompiledCommandMetadataProvider` в CQRS v4 строго map-backed. Отсутствующая metadata возвращает `null` и не появляется неявно только потому, что command class существует в runtime. Это выравнивает development discovery и compiled execution.

`ReflectionCommandMetadataProvider` остаётся только явной альтернативной реализацией.

Дополнительные пакеты добавляют свои metadata classes через `ConfigKey::COMMAND_METADATA_ATTRIBUTES`, чтобы `componenta/cqrs-app` обнаруживал и компилировал одинаковые descriptors.

## Discovery attributes

`componenta/cqrs-app` понимает:

```php
#[Componenta\CQRS\Command\Attribute\AsCommandHandler]
#[Componenta\CQRS\Command\Attribute\AsCommandListener]
#[Componenta\CQRS\Query\Attribute\AsQueryHandler]
```

Message handler должен занимать первый parameter slot. Дополнительные обязательные параметры handler не dependency-inject-ятся CQRS runtime.

## Дополнительные пакеты

| Пакет | Назначение |
|---|---|
| `componenta/cqrs-app` | Development discovery и build-time compilation map |
| `componenta/cqrs-policy` | Авторизация command/query |
| `componenta/cqrs-retry` | Retry metadata и middleware |
| `componenta/cqrs-lock` | Resource locking |
| `componenta/cqrs-transaction-cycle` | Cycle Database transactions |
| `componenta/cqrs-transport` | Async transport contracts, serializers, middleware, worker |
| `componenta/cqrs-transport-cycle` | Cycle Database transport |
| `componenta/cqrs-transport-console` | Symfony Console worker |

## Проверка

```bash
composer test
composer analyse
composer bench
```

CI ориентирован на PHP 8.4/8.5, запускает Pest и PHPStan на максимальном уровне; обычный test suite также загружает benchmark setup как лёгкую проверку совместимости API.
