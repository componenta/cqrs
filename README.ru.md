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
use Componenta\Config\ConfigLoader;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\HandlerDescriptor;
use Componenta\DI\ContainerBuilder;

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

$config = ConfigLoader::load(
    null,
    new Componenta\CQRS\ConfigProvider(),
    static fn(): array => [
        ConfigKey::CQRS_MAP => (new CqrsMap(
            commandHandlers: [
                CalculateTotalCommand::class
                    => new HandlerDescriptor(CalculateTotalHandler::class, '__invoke'),
            ],
        ))->toArray(),
    ],
);
$container = ContainerBuilder::configure($config)->build();

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
| `CommandHandlerLocatorInterface` | Разрешает дескрипторы обработчиков команд из активного `CqrsMapProviderInterface`. |
| `CommandListenersLocatorInterface` | Фильтрует и разрешает дескрипторы слушателей из того же снимка карты. |
| `QueryHandlerLocatorInterface` | Разрешает дескрипторы обработчиков запросов из того же снимка карты. |
| `CommandMetadataProviderInterface` | Возвращает любой зарегистрированный тип атрибута команды из карты v2; reflection используется только для неизвестных карте команд. |

Основные ключи:

| Ключ | Значение |
|---|---|
| `ConfigKey::COMMAND_MIDDLEWARES` | Список command middleware в порядке выполнения. |
| `ConfigKey::QUERY_MIDDLEWARES` | Список query middleware в порядке выполнения. |
| `ConfigKey::CQRS_MAP` | Единый версионированный артефакт с обработчиками команд и запросов, слушателями, метаданными и известными командами. |
| `ConfigKey::COMMAND_METADATA_ATTRIBUTES` | Классы атрибутов, которые должен обнаружить и скомпилировать `componenta/cqrs-app`. Дополнительные пакеты добавляют сюда свои атрибуты. |

Отсутствие ключа middleware означает пустую цепочку. Middleware из дополнительных пакетов добавляется приложением, когда нужны проверки прав, повторные попытки, блокировки, транзакции, асинхронное выполнение или воркеры.

Пустые секции карты не записываются: пустой валидный артефакт имеет вид `['version' => 2]`. В `APP_ENV=production` актуальная карта обязательна. В остальных окружениях `componenta/cqrs-app` может добавить к пустой базовой карте данные текущего обнаружения классов. Старые ключи карт и неподдерживаемые версии завершаются ошибкой с требованием очистить кеши и выполнить `app:build`.

## Команды

Команда — объект с данными для сценария, который меняет состояние. `CommandBusInterface::dispatch(object $command, array $attributes = [])` возвращает immutable `OperationInterface` с id операции, attributes и result, если команда выполнена синхронно.

Обработчики ищутся через `CommandHandlerLocatorInterface`. Дескриптор карты хранит id сервиса и метод; callable лениво разрешается через контейнер и запоминается локатором.

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
| `#[Componenta\CQRS\Transport\Attribute\Async]` | класс команды | `string $transport = 'default', int $delay = 0` | Метаданные из `componenta/cqrs-transport`. |
| `#[Componenta\CQRS\Retry\Attribute\Retry]` | класс команды | `int $attempts = 3, int $delayMs = 100, float $multiplier = 1.0, int $maxDelayMs = 10000` | Метаданные из `componenta/cqrs-retry`. |
| `#[Componenta\CQRS\Lock\Attribute\Lock]` | класс команды | `string $key, float $ttl = 300.0, bool $blocking = true` | Метаданные из `componenta/cqrs-lock`. |

## События команд

| Event | Когда |
|---|---|
| `CommandProcessEvent` | До выполнения команды. |
| `CommandProcessedEvent` | После успешного выполнения. |
| `CommandFailedEvent` | После ошибки, перед повторным выбросом exception. |

Listeners ищутся через `CommandListenersLocatorInterface`. Ошибки listener-ов по умолчанию распространяются одинаково во всех окружениях. Явно созданный `EventMiddleware` может использовать `suppressExceptions: true`, чтобы игнорировать только исключения из тела listener-а; ошибки locator, карты и DI всегда распространяются.

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
| Метаданные команд | `CommandMetadataProviderInterface` | Метод `get($command, Attribute::class)` универсален; дополнительные пакеты регистрируют классы атрибутов через config. |

## Ошибки

| Ошибка | Exception |
|---|---|
| Command handler не найден | `Componenta\CQRS\Command\Exception\HandlerNotFoundException` |
| Некорректный command handler | `Componenta\CQRS\Command\Exception\InvalidHandlerException` |
| Query handler не найден | `Componenta\CQRS\Query\Exception\HandlerNotFoundException` |

## Переход с v1

| v1 | v2 |
|---|---|
| `COMMAND_HANDLER_MAP` | `CQRS_MAP['commands']['handlers']` |
| `QUERY_HANDLER_MAP` | `CQRS_MAP['queries']['handlers']` |
| `COMMAND_LISTENER_MAP` | `CQRS_MAP['commands']['listeners']` |
| `COMMAND_ATTRIBUTE_MAP` | `CQRS_MAP['commands']['metadata']` |
| `COMPILED_MAPS` | Удалён: режим определяется версией карты и окружением. |
| `CommandAttributeProviderInterface` | `CommandMetadataProviderInterface` |
| `async()/retry()/lock()` | `get($command, Attribute::class)` |

После обновления удалите кеши конфигурации, обнаружения классов, старых CQRS-карт, generated resolver и release fingerprint, затем выполните `APP_ENV=development php bin/console.php app:build`.
