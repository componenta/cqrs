# Componenta CQRS

`componenta/cqrs` — библиотека CQRS для PHP 8.5.13+ и Componenta DI 6.

```bash
composer require componenta/cqrs
```

Зарегистрируйте `Componenta\CQRS\ConfigProvider`. Он предоставляет стандартные command/query bus, locators, operation factory, провайдер метаданных и общий сервис массивов регистраций.

## Команды и операции

```php
$operation = $commands->dispatch(new CreateUserCommand($email));
$result = $operation->result?->value;
```

Один `dispatch()` создаёт одну `OperationInterface` и проводит её через полный command pipeline.

Operation содержит:

- UUID `id` (по умолчанию UUIDv7);
- экземпляр `command`;
- `createdAt` — время создания операции для отправки на обработку (по умолчанию UTC);
- `attributes` со строгим контрактом `array<string,mixed>`;
- необязательный `OperationResult`, где `processedAt` фиксирует синхронное завершение.

Ненулевой `result` означает завершение для любой реализации `OperationInterface`, даже если `result->value` равен `null`. Метод `withResult()` устанавливает результат только один раз. Терминальный обработчик отклоняет завершённую операцию до поиска и вызова обработчика команды. Эта проверка не обеспечивает распределённую идемпотентность.

Поле называется именно `createdAt`, а не `startedAt`: operation создаётся до middleware и это не является моментом начала handler. Async transport не должен восстанавливать producer `createdAt` как время начала выполнения worker.

В CQRS operation factory отделён от middleware composition:

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

Если фабрика не передана, используется `OperationFactory`.

### Настройка часов и UUID

Стандартная фабрика операций зависит от часов PSR-20 (`ClockInterface`) и генератора Identity (`UuidFactoryInterface`). По умолчанию используются встроенные `Componenta\CQRS\Clock\UtcClock` и стандартный генератор UUIDv7. Часы не допускают `null`: чтобы использовать реализацию по умолчанию, не передавайте аргумент.

```php
use Componenta\CQRS\Clock\UtcClock;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationFactory;
use Componenta\Identity\UuidFactory;

$clock = new UtcClock();
$factory = new OperationFactory(clock: $clock, uuidFactory: new UuidFactory());
$operation = $factory->create($command);

// При необходимости передаются уже полученные значения:
$operation = Operation::create(
    command: $command,
    createdAt: $clock->now(),
);
```

`Operation::create()` принимает готовый `DateTimeImmutable` и необязательный UUID Identity; при отсутствии соответствующего значения используются текущее время UTC и UUIDv7. Операция и её неизменяемые копии сохраняют переданные объекты, часовой пояс и микросекунды. Для идентификатора операции используется минимальный `Componenta\Identity\UuidInterface`. Объекты Ramsey UUID больше не удовлетворяют этому контракту.

При использовании провайдера конфигурации CQRS зарегистрированные `Psr\Clock\ClockInterface` и `Componenta\Identity\UuidFactoryInterface` внедряются автоматически. Обработчик получает время после успешного вызова, события — при создании и при первом успешном `stop()`. Часовой пояс настроенных часов сохраняется. Если сервис часов не зарегистрирован, каждый этап использует `UtcClock`; явно зарегистрированный `null` или сервис неверного типа считается ошибкой конфигурации. Ошибки часов обрабатываются по действующим правилам исключений команд и слушателей. Нормализация отметок и гарантия монотонности системного времени не предусмотрены. Если пользовательские часы выбрасывают исключение после вызова обработчика, его побочные эффекты уже могли произойти.

Для замены фабрики операций через DI укажите в провайдере приложения новый целевой идентификатор сервиса для псевдонима `OperationFactoryInterface`. Стандартный конкретный класс `OperationFactory` останется доступен. Не регистрируйте конфликтующие фабрики и сервисы для одного канонического идентификатора DI.

### Несколько команд

`BatchCommandBus` — явный декоратор последовательного multi-dispatch:

```php
$batch = new Componenta\CQRS\Command\BatchCommandBus($commands);

$operations = $batch->dispatchMany([
    new FirstCommand(),
    new SecondCommand(),
]);
```

Каждая команда независимо проходит wrapped bus и получает собственную operation. Явное значение `null` (как и любое не-массивное значение) для атрибутов выбранного класса отклоняется до отправки первой команды; отсутствие записи означает `[]`. Batch выполняется последовательно и не обеспечивает транзакционности. `SequentialMiddleware` удалён; nested `dispatch()` имеет обычную reentrant-семантику. Если действие должно произойти после текущей команды или транзакции, используйте явный event, outbox, async transport или workflow/process manager.

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

**Retry и ошибки уведомления.** При порядке `Retry → Event → handler` ошибка `CommandProcessedEvent` может вызвать повторный запуск уже успешно отработавшего handler. При `Event → Retry → handler` события относятся ко всему dispatch, а не к отдельным попыткам; промежуточные ошибки, устранённые внутренним retry, не создают внешний `CommandFailedEvent`. Ни один порядок не гарантирует идемпотентности. Приложению нужны выбранная семантика retry/transaction и, при необходимости, ключ идемпотентности либо транзакционный outbox. См. [план](docs/plans/cqrs-operation-lifecycle-contracts.md).

## Queries

```php
$result = $queries->handle(new GetUserQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` нормализует array context в immutable `Context`. Ключи query context подчиняются тому же non-empty string-key контракту, что и operation attributes. Встроенные `Context` и `Operation` отсоединяют PHP-ссылки верхнего уровня; вложенные объекты и ресурсы не клонируются глубоко.

## Регистрация и карты

Ядро CQRS работает с массивами; поиск классов и загрузку файлов выполняет пакет интеграции. Требуются Config 3 и DI 6. Пакеты и приложение добавляют списки регистраций через ConfigProvider:

```php
use Componenta\CQRS\ConfigKey;

final class ApplicationConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::COMMAND_HANDLERS => [
                ['message' => CreateUserCommand::class, 'service' => CreateUserHandler::class, 'method' => '__invoke'],
            ],
            ConfigKey::QUERY_HANDLERS => [
                ['message' => GetUserQuery::class, 'service' => GetUserHandler::class, 'method' => 'handle'],
            ],
            ConfigKey::COMMAND_LISTENERS => [
                ['message' => CreateUserCommand::class, 'service' => AuditListener::class, 'priority' => 10,
                 'events' => [\Componenta\CQRS\Command\Event\CommandProcessedEvent::class]],
            ],
        ];
    }
}
```

Провайдер ядра регистрируют перед провайдерами приложения. Обработчики и слушатели создаются обычными средствами DI. Общий сервис `cqrs.maps` содержит три массива: `command_handlers`, `query_handlers`, `command_listeners`. Каждый локатор получает свою секцию.

При прямом создании локатора массив обработчиков индексируется именем сообщения:

```php
$locator = new \Componenta\CQRS\Command\Locator\CommandHandlerLocator([
    CreateUserCommand::class => ['service' => CreateUserHandler::class, 'method' => '__invoke'],
], $container);

$command = new CreateUserCommand($email);
$handler = $locator->locateFor($command);
$handler($command); // Прямой вызов, если не требуются дополнительные аргументы.
```

Одинаковые регистрации обработчика объединяются. Разные обработчики одного имени вызывают `CqrsMapConflictException`; повтор одинаковой явной регистрации слушателя — `InvalidCqrsMapException`. Слушатели выполняются по убыванию приоритета, затем по ID сервиса и каноническому списку событий. Пустой список событий означает все события жизненного цикла команды.

Имя определяется для каждого экземпляра сообщения. `supports()` не создаёт обработчик; каждое обращение к `locateFor()` получает сервис через контейнер с соблюдением его правил жизненного цикла. Слушатели фильтруются по событию до получения сервиса.

[componenta/cqrs-app](https://github.com/componenta/cqrs-app) добавляет поиск по атрибутам и сборку через `app:build`. Готовый актуальный файл позволяет пропустить поиск; при отсутствии или повреждении файла используются исходники. Оба пути используют одинаковые локаторы и правила регистрации.

## Разрешение имён и проверка поддержки

Резолверы имён команд и запросов реализуют соответствующие интерфейсы. Их контракты не зависят от наличия зарегистрированных обработчиков:

- `supports(message)` — возвращает true, если резолвер может определить имя сообщения. Значение false означает отсутствие поддержки и не является ошибкой.
- `resolve(message)` — возвращает имя для поиска либо выбрасывает соответствующее исключение `UnsupportedCommandException` / `UnsupportedQueryException`, если сообщение не поддерживается. Иные ошибки определения имени распространяются без преобразования.

Стандартные резолверы поддерживают любой объект: используют явное имя из `NamedCommandInterface` / `NamedQueryInterface` либо имя PHP-класса. Составной резолвер выбирает первый поддерживающий резолвер в порядке регистрации, затем проверяет резервный. Если сообщение не поддерживает никто, `supports()` возвращает false, а `resolve()` выбрасывает соответствующее исключение. Ошибка выбранного резолвера не приводит к переключению на резервный.

Стандартные локаторы используют внедрённый резолвер имён, только если он поддерживает сообщение; иначе применяются стандартные правила определения имени. Собственный метод `supports()` локатора проверяет его применимость к сообщению (для локатора на основе массива — наличие соответствующей регистрации), не создавая и не вызывая обработчик. Значение true не гарантирует успешного получения сервиса. Составной локатор делегирует поиск первому поддерживающему локатору либо резервному локатору и распространяет ошибки выбранного делегата.

## Исключения и ошибки обработки

Шины команд и запросов передают вызывающему коду ошибки соответствующего этапа обработки без обёртывания. Общий интерфейс `Componenta\CQRS\Exception\ExceptionInterface` наследуется от `Throwable` и обозначает все собственные исключения CQRS. Контракты шин используют этот интерфейс для ошибок CQRS и `Throwable` для остальных ошибок независимо от выбранного контейнера и механизма вызова. Более узкие контракты указывают конкретные исключения CQRS, если ошибка является частью их семантики: неподдерживаемое сообщение, отсутствие обработчика, некорректный аргумент или повторное завершение операции. Иные ошибки, когда они возможны, описываются через `Throwable`. Конкретные классы исключений сохраняются для точечной обработки и наследуют прежние типы SPL. Внешнее исключение не становится исключением CQRS только потому, что прошло через шину. Исключения поиска обработчика возникают, только если выполнение дошло до терминального обработчика:

| Ситуация | Исключение |
| --- | --- |
| Нет регистрации обработчика команды | `Command\Exception\HandlerNotFoundException` |
| Нет регистрации обработчика запроса | `Query\Exception\HandlerNotFoundException` |
| Зарегистрированный сервис команды не соответствует описанию вызываемого обработчика | `Command\Exception\InvalidHandlerException` |
| Зарегистрированный сервис запроса не соответствует описанию вызываемого обработчика | `Query\Exception\InvalidHandlerException` |
| Некорректные атрибуты операции или массива контекста запроса | `Exception\InvalidArgumentException` |
| Некорректная конфигурация CQRS | `Exception\InvalidConfigurationException` |
| Повторная обработка завершённой операции | `Command\Exception\OperationAlreadyCompletedException` |
| Зарегистрированный сервис отсутствует или не может быть создан | Исходное внешнее исключение (`Throwable`) |
| Ошибка подготовки вызова или разрешения аргументов | Исходное внешнее исключение (`Throwable`) |
| Ошибка обработчика или промежуточного обработчика | Исходное распространяемое исключение |

Имена исключений CQRS в таблице указаны относительно пространства имён `Componenta\CQRS`. `HandlerNotFoundException` содержит разрешённое логическое имя сообщения; отсутствие сервиса при существующей регистрации — ошибка контейнера, а не отсутствие регистрации обработчика.

Промежуточный обработчик может вернуть результат без вызова терминального обработчика. Результат запроса null допустим; при отложенной обработке результат операции команды может отсутствовать. У синхронно выполненной команды, возвращающей null, существует `OperationResult` со значением null. Ни одна шина не превращает ошибку в результат null.

`EventMiddleware` может подавлять ошибки непосредственно в слушателях, если это явно настроено. Если уведомление слушателей об ошибке обработки также завершилось ошибкой, `CommandFailureNotificationException` сохраняет оба исключения. Сами шины не перехватывают и не преобразуют исключения. Некорректные регистрации и настройки промежуточных обработчиков отклоняются при построении карт и цепочек обработки, а не выдаются за отсутствие регистрации обработчика.

## Метаданные

`CommandMetadataProviderInterface` предоставляет одну операцию:

```php
$attribute = $metadata->get($command, MyAttribute::class);
```

Стандартный `ReflectionCommandMetadataProvider` читает класс команды независимо от карт и регистрации обработчика. Каждое обращение создаёт свежий атрибут, включая объектные аргументы. Отсутствующий атрибут или класс команды возвращает `null`. Стандартный провайдер использует ограниченный кеш отражений загруженных классов, но не кеширует экземпляры атрибутов и аргументы их конструкторов. Недоступный класс повторно проверяется при последующих обращениях. Некорректное объявление, повтор атрибута или ошибка его создания вызывает `InvalidCommandMetadataException`; ошибка создания сохраняется как исходная причина.

Retry, lock и transport используют этот провайдер. Дополнительная регистрация или компиляция их атрибутов не требуется.

## Discovery attributes

`componenta/cqrs-app` понимает:

```php
#[Componenta\CQRS\Command\Attribute\AsCommandHandler]
#[Componenta\CQRS\Command\Attribute\AsCommandListener(CreateUserCommand::class)]
#[Componenta\CQRS\Query\Attribute\AsQueryHandler]
```

Message handler должен занимать первый parameter slot. CQRS передаёт этот аргумент и делегирует вызов настроенному `CallableInvokerInterface`. Стандартный `CallableInvoker` не внедряет отсутствующие аргументы; DI-aware реализация может добавить зависимости. Поиск обработчиков по атрибутам сам инъекцию не выполняет.

Терминальные обработчики передают найденный локатором вызываемый объект или другое представление `callable` и сообщение настроенному механизму вызова без преобразований. Интерпретация `callable`, проверка доступности метода и разрешение аргументов относятся к ответственности механизма вызова. В DI 6 точный строковый идентификатор сервиса имеет приоритет перед синтаксисом `callable`. Если необходимо однозначно выбрать нативный обработчик, локатор может вернуть, например, замыкание, созданное синтаксисом first-class callable, или массив с публичным статическим методом.

## Дополнительные пакеты

| Пакет | Назначение |
|---|---|
| `componenta/cqrs-app` | Поиск по атрибутам и явная сборка карт |
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
composer test:negative
composer bench
```

CI запускает Pest и PHPStan на максимальном уровне на PHP 8.5, а на PHP 8.6 — проверку основных сценариев изолированного потребителя с минимальными поддерживаемыми версиями производственных зависимостей. При проверке разрабатываемой версии CI использует зафиксированный коммит DI 6; при выпуске устанавливаются объявленные стабильные зависимости. Набор PHPBench отдельно измеряет построение карт, маршрутизацию, работу с атрибутами и метаданными, выполнение команд и запросов через реальный DI, создание операций и результатов, генерацию UUID, часы UTC и создание событий с остановкой распространения. При изменении зависимостей сравнение выполняется в изолированных установках с одинаковыми версиями общих зависимостей. Composer проверяет весь набор зависимостей разработки на известные уязвимости и выполняет строгую проверку производственных зависимостей.
