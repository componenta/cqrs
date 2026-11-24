# CQRS 5: интеграция PSR-20 и миграция на Componenta Identity

**Статус:** реализация согласованных контрактов и интеграции; приёмка выпуска не завершена.  
**Дата:** 2026-11-24.  
**Исходная ревизия:** 35de3ba509292281150414f40e595712432b749f.  
**Проверенные зависимости:** DI 6 (2361c398fc938108de67df8ec6a723bf4c4648ff), Identity v1.1.0 (e036d5d787379b69cf85d8c3ae83db732590b676), Clock v1.1.0 (a97b782bd17e413864ba24d90d86b75297b7b99d).

## 1. Цель и обязательные требования

- В компоненты, самостоятельно получающие время, внедрять PSR-20 ClockInterface без nullable-типа. По умолчанию использовать Componenta\CQRS\Clock\UtcClock; явный null недопустим. Operation и OperationResult принимают готовые временные отметки и не хранят часы.
- Принимать готовые DateTimeImmutable; сохранять исходный объект, timezone и микросекунды. Не вызывать setTimezone() над переданным значением.
- Если в Operation::create или OperationResult не передана временная отметка либо передан null, получать текущее время через UtcClock.
- Не подменять ошибку ClockInterface::now() системным временем.
- Заменить прямую зависимость от Ramsey UUID на собственную реализацию componenta/identity.
- Сохранить имена и порядок существующих параметров; новые параметры — только в конце. Сигнатуры шин, middleware, обработчиков и OperationFactoryInterface не расширять.
- Сохранить порядок событий, правила обработки исключений, shallow immutability, проверку ключей атрибутов и семантику null-результата. Не изменять retry/transaction семантику.
- Не вводить глобальные часы, сеттеры зависимостей, новые публичные интерфейсы, фабрики результатов или событий без необходимости. Не менять Identity, Clock, DI 6 и интеграционные пакеты в рамках данного плана.

## 2. Согласованные решения

1. Конструкторное внедрение ClockInterface со значением по умолчанию new UtcClock() вместо nullable-свойств, ClockAwareInterface или setClock(). Сохраняется readonly компонентов.
2. Operation::create принимает дополнительный готовый UUID, чтобы OperationFactory могла использовать UuidFactoryInterface без хранения генератора внутри операции.
3. CommandEvent получает приватные readonly часы от EventMiddleware и самостоятельно фиксирует dispatchedAt при создании, а propagationStoppedAt при первом успешном вызове stop(). Метод stop() не принимает временную отметку. При отсутствии явно настроенных часов используется UtcClock.
4. Стандартный генератор — UUID v7; явно настроенный UuidFactoryInterface определяет версию сам. Тип id — минимальный Componenta\Identity\UuidInterface.
5. UUID v7 и createdAt не обязаны иметь одинаковую временную составляющую: текущий Identity использует собственный источник времени и не принимает timestamp в generate()/v7(). Не менять алгоритм генерации ради их синхронизации.
6. Для прямого Operation::create без id — повторно используемый приватный генератор по умолчанию; для штатной диспетчеризации — внедрённый или собственный генератор OperationFactory. Не создавать фабрику UUID на каждом dispatch.
7. Зафиксировать требование 64-битного PHP, поскольку стандартный UuidFactory Identity требует 64-bit. Это осознанное ограничение платформы CQRS 5.

## 3. Инвентаризация и зависимости

Проверены 79 файлов src/ исходной ревизии. Четыре явных обращения к системному времени, плюс отдельное время генерации UUID:

| Место | Сейчас | После |
| --- | --- | --- |
| Operation::create | Ramsey Uuid::uuid7() | Готовый UUID или Identity v7 |
| Operation::create | now UTC | Готовый createdAt или UtcClock |
| OperationResult::__construct | Готовый processedAt или now UTC | Готовый processedAt или UtcClock |
| CommandEvent::__construct | now UTC | clock->now() |
| CommandEvent::stop | Первое время остановки UTC | clock->now() при первом успешном stop() |

Часы нужны OperationFactory, HandleCommandHandler, EventMiddleware и CommandEvent. Встроенный UtcClock — неизменяемая реализация ClockInterface; now() возвращает новый DateTimeImmutable в UTC независимо от часового пояса PHP. Это единственная точка получения системного UTC в рабочем коде CQRS. В QueryBus, Context, PipelineHandler, BatchCommandBus, локаторы и резолверы не внедрять: они не получают время самостоятельно.

Целевые производственные зависимости:

~~~json
{
    "require": {
        "php": "^8.5.13",
        "php-64bit": "*",
        "componenta/config": "^3.0.0",
        "componenta/di": "^6.0",
        "componenta/identity": "^1.1.0",
        "componenta/reflection": "^2.0.1",
        "psr/clock": "^1.0",
        "psr/container": "^2.0"
    }
}
~~~

Удалить ramsey/uuid. Не использовать componenta/uuid: это legacy-провайдер Ramsey. componenta/clock — опциональная реализация PSR-20, не обязательная производственная зависимость CQRS. Тесты могут использовать собственные PSR-20 doubles; для проверки официального Clock ConfigProvider допустима отдельная dev/integration зависимость.

Сохранить minimum-stability: stable. DI 6 до релиза подключать через существующий временный механизм CI; не добавлять dev-ограничение в распространяемый composer.json.

## 4. Операции

### 4.1. OperationInterface и Operation

Заменить импорт Ramsey\Uuid\UuidInterface на Componenta\Identity\UuidInterface в интерфейсе и реализации. Свойство сохраняет имя и режим чтения:

~~~php
use Componenta\Identity\UuidInterface;

public UuidInterface $id { get; }
~~~

Конструктор Operation не получает ClockInterface или UuidFactoryInterface. Существующий порядок параметров:

~~~php
public function __construct(
    public UuidInterface $id,
    public object $command,
    public DateTimeImmutable $createdAt,
    array $attributes = [],
    public ?OperationResult $result = null,
)
~~~

Целевой статический метод (необходимые imports подразумеваются):

~~~php
public static function create(
    object $command,
    array $attributes = [],
    ?DateTimeImmutable $createdAt = null,
    ?UuidInterface $id = null,
): self {
    if ($id === null) {
        static $defaultUuidFactory = new UuidFactory();
        $id = $defaultUuidFactory->generate();
    }

    return new self(
        id: $id,
        command: $command,
        createdAt: $createdAt ?? (new UtcClock())->now(),
        attributes: $attributes,
    );
}
~~~

Фабрика из Componenta\Identity. Внутренний static относится только к standalone-пути: сохраняет повторное использование генератора, как ранее у Ramsey, не является глобальным конфигурируемым сервисом. При явно переданном id этот путь не инициализируется.

Альтернатива без внутреннего static — новый UuidFactory в standalone-пути; требуется отдельно оценить влияние на скорость и наблюдаемую монотонность UUID. В штатном dispatch генератор всегда переиспользуется.

- Переданный id и createdAt сохраняются по идентичности.
- При отсутствии id генерируется UUID v7, независимо от timestamp.
- withResult/withAttribute/withAttributes/withoutAttribute сохраняют id, createdAt и остальные неизменяемые данные по действующему контракту, не вызывают генератор и часы.
- Не ослаблять валидацию атрибутов и guard завершённой операции; сохранить оптимизацию clone-with PHP 8.5.
- Не требовать RfcUuidInterface от произвольных пользовательских UUID. Не обещать абсолютную глобальную монотонность между экземплярами генераторов и процессами. Identity может использовать синтетическое увеличение времени UUID при переполнении последовательности.

### 4.2. OperationFactory

~~~php
final readonly class OperationFactory
    implements OperationFactoryInterface
{
    public function __construct(
        private ClockInterface $clock = new UtcClock(),
        private UuidFactoryInterface $uuidFactory = new UuidFactory(),
    ) {}

    public function create(
        object $command,
        array $attributes = [],
    ): OperationInterface {
        return Operation::create(
            command: $command,
            attributes: $attributes,
            createdAt: $this->clock->now(),
            id: $this->uuidFactory->generate(),
        );
    }
}
~~~

Один генератор на экземпляр фабрики. ClockInterface и UuidFactoryInterface — зависимости, а не изменяемые настройки. Каждый create получает новое время и новый id. При исключении now() не вызывать generate(), при сбое любого из источников не создавать операцию и не запускать pipeline. Сохранить исходный Throwable.

OperationFactoryInterface::create(object $command, array $attributes = []): OperationInterface остаётся неизменным. Метод restore() не добавляется: транспортные пакеты передают команду и разрешённый контекст, а не восстанавливают полную операцию. Уточнить документацию: createdAt — момент создания, UTC только по умолчанию; версия UUID определяется генератором, стандартная — v7. Не добавлять требования к версии в минимальный интерфейс.

## 5. Результаты и события

### 5.1. OperationResult / HandleCommandHandler

OperationResult сохраняет существующий конструктор:

~~~php
public function __construct(
    public mixed $value,
    ?DateTimeImmutable $processedAt = null,
) {
    $this->processedAt = $processedAt
        ?? (new UtcClock())->now();
}
~~~

HandleCommandHandler получает последний необязательный аргумент:

~~~php
public function __construct(
    private CommandHandlerLocatorInterface $locator,
    private CallableInvokerInterface $invoker = new CallableInvoker(),
    private ClockInterface $clock = new UtcClock(),
) {}
~~~

После существующих guard и lookup:

~~~php
$value = $this->invoker->call($handler, [$operation->command]);

return $operation->withResult(
    new OperationResult(
        $value,
        processedAt: $this->clock->now(),
    ),
);
~~~

Время завершения читается после успешного вызова обработчика, один раз. Если invoker выбрасывает исключение, now() для результата не вызывается. Guard завершённой операции срабатывает до locator, invoker, clock. Null остаётся допустимым значением результата.

### 5.2. CommandEvent и CommandFailedEvent

Событие само фиксирует момент своего создания через ClockInterface (UtcClock по умолчанию). Отметку извне не принимает.

~~~php
// CommandEvent
public function __construct(
    public readonly OperationInterface $operation,
    private readonly ClockInterface $clock = new UtcClock(),
) {
    $this->dispatchedAt = $this->clock->now();
}
~~~

CommandProcessEvent и CommandProcessedEvent наследуют конструктор. CommandFailedEvent сохраняет порядок существующих аргументов:

~~~php
public function __construct(
    OperationInterface $operation,
    public readonly Throwable $exception,
    ClockInterface $clock = new UtcClock(),
) {
    parent::__construct($operation, $clock);
}
~~~

Сохранить идентичность exception, operation и возвращаемой часами даты. Не подменять время события временем операции.

### 5.3. Остановка распространения

~~~php
public function stop(): void
{
    if ($this->propagationStoppedAt === null) {
        $this->propagationStoppedAt = $this->clock->now();
    }
}
~~~

Первый успешный stop() получает отметку в момент вызова; последующие вызовы не читают часы и не меняют состояние. Слушатель не передаёт произвольную дату. При ошибке часов отметка остаётся null; ошибка распространяется вызывающему коду. Если ошибка возникла внутри слушателя, действуют существующие правила подавления ошибок слушателей. Событие не отменяет выполнение команды. Часы хранятся только в приватном readonly свойстве события и не являются его публичным состоянием.

### 5.4. EventMiddleware

~~~php
public function __construct(
    private CommandListenersLocatorInterface $locator,
    private bool $suppressExceptions = false,
    private ClockInterface $clock = new UtcClock(),
) {}
~~~

В каждой существующей ветви execute передаётся одна и та же ссылка на часы:

~~~php
// До downstream.
$this->dispatch(new CommandProcessEvent($operation, $this->clock));

// В существующей ветви catch downstream failure.
$this->dispatch(new CommandFailedEvent($operation, $exception, $this->clock));

// После успешного возврата downstream.
$this->dispatch(new CommandProcessedEvent($operation, $this->clock));
~~~

Failed и Processed взаимоисключающие. Порядок и границы try/catch сохраняются. Время не читается заранее; stop() использует тот же источник времени. Processed означает успешный возврат downstream, не гарантирует наличия result при отложенной обработке.

## 6. DI 6

### 6.1. Регистрация

В текущем ConfigProvider OperationFactoryInterface зарегистрирован через keyed invokable. DI 6 InvokableResolver вызывает new $class() без аргументов. Добавление optional constructor dependency без изменения регистрации не внедрит часы приложения.

Заменить запись getInvokables следующей конфигурацией; остальные фабрики сохранить:

~~~php
protected function getAliases(): array
{
    return [
        OperationFactoryInterface::class => OperationFactory::class,
    ];
}

protected function getFactories(): array
{
    return [
        OperationFactory::class => OperationFactoryFactory::class,
        // остальные существующие регистрации
    ];
}
~~~

Сохранить доступ по обоим существующим идентификаторам, shared instance при get, возможность подмены/декорирования фабрики. Удалить старую invokable-регистрацию.

### 6.2. Разрешение зависимостей с реализациями по умолчанию

Новая Command\Factory\OperationFactoryFactory::__invoke(ContainerInterface $container): OperationFactory.

| Условие | Действие |
| --- | --- |
| ClockInterface отсутствует | Создать UtcClock |
| ClockInterface зарегистрирован | Получить и проверить instanceof ClockInterface |
| UuidFactoryInterface отсутствует | Создать один стандартный UuidFactory |
| UuidFactoryInterface зарегистрирован | Использовать полученный объект после проверки типа |
| Зарегистрированное значение равно null или неверного типа | CQRS\Exception\InvalidConfigurationException с id и ожидаемым типом |
| has/get выбрасывает исключение | Распространить исходный Throwable |

Пример получения часов с различением отсутствия и ошибочного null:

~~~php
$clock = $container->has(ClockInterface::class)
    ? $container->get(ClockInterface::class)
    : new UtcClock();

if (!$clock instanceof ClockInterface) {
    throw new InvalidConfigurationException(sprintf(
        'Container entry "%s" must implement %s.',
        ClockInterface::class,
        ClockInterface::class,
    ));
}
~~~

Аналогичная проверка для UuidFactoryInterface; не создавать fallback заранее, когда dependency зарегистрирована. Вызовы has/get не оборачивать. Не вводить новый публичный helper ради повторения этих проверок.

| Файл | Изменение |
| --- | --- |
| Command/Factory/OperationFactoryFactory.php | Новый composition factory из § 6.2 |
| Command/Factory/CommandBusFactory.php | При отсутствии OperationFactoryInterface вызвать новый factory вместо new OperationFactory(), чтобы не проигнорировать настройки |
| Command/Factory/HandleCommandHandlerFactory.php | Разрешить ClockInterface (UtcClock при отсутствии регистрации); передать последним аргументом |
| Command/Factory/EventMiddlewareFactory.php | Разрешить ClockInterface через PSR-11 has/get (UtcClock при отсутствии регистрации); передать clock по имени, оставить suppressExceptions |
| ConfigProvider.php | Регистрация/алиас из § 6.1 |

Если пользователь уже предоставил OperationFactoryInterface, не заменять её и не искать лишние UUID/clock для её создания. CQRS не регистрирует и не переопределяет сами ClockInterface или UuidFactoryInterface: это зона приложения и существующих Clock/Identity ConfigProvider.

Разрешение зависимостей выполняется при создании компонента, не при каждом dispatch. Замена binding не обязана менять ссылку уже созданного readonly-компонента. Изменение состояния внедрённого FrozenClock отражается при следующем вызове now().

Пример ручной сборки:

~~~php
$factory = new OperationFactory(clock: $clock, uuidFactory: $uuidFactory);
$handler = new HandleCommandHandler($handlerLocator, $invoker, clock: $clock);
$events = new EventMiddleware($listenerLocator, clock: $clock);

$bus = new CommandBus(
    commandHandler: $handler,
    middlewares: [$events],
    operationFactory: $factory,
);
~~~

## 7. Ошибки и совместимость

Собственные исключения CQRS реализуют CQRS\Exception\ExceptionInterface; узкие контракты используют специализированные исключения, внешние ошибки покрываются Throwable.

| Ошибка now() | Поведение |
| --- | --- |
| Создание операции | Не запускать pipeline |
| Создание ProcessEvent | Не запускать downstream |
| Создание результата после invoker | Downstream завершается ошибкой; существующий EventMiddleware может уведомить о Failed |
| Создание FailedEvent после первичной ошибки | CommandFailureNotificationException сохраняет обе причины |
| Создание ProcessedEvent | Ошибка распространяется после downstream; нового FailedEvent не создавать |

Ошибки создания события не являются подавляемыми ошибками тела слушателя. Если слушатель самостоятельно вызывает часы, применяется обычное правило suppression. Не обещать отсутствие побочных эффектов при ошибке после выполнения обработчика. Связанный план: cqrs-operation-lifecycle-contracts.md.

PSR-20 не гарантирует монотонность. Не переставлять события, не выравнивать время, не отклонять равные или уменьшающиеся отметки. Для будущих измерений длительности использовать монотонный источник отдельно.

**Breaking changes CQRS 5:**
- Тип OperationInterface::$id и аргумента конструктора Operation — Componenta\Identity\UuidInterface.
- Пользовательские реализации OperationInterface, декораторы, типы сериализаторов и хранилищ должны мигрировать.
- Каноническая строковая форма UUID сохраняется; строковые идентификаторы не нужно менять. Нативная PHP-сериализация Ramsey-объектов, методы getBytes/getFields/getDateTime и другие зависимости от расширенного API требуют проверки интеграций. Не вводить Ramsey-адаптер в ядро.
- Минимальный контракт Identity не требует зависимости от RFC-специфических полей; не наследовать IdentityInterface с другим именем свойства uuid.
- Платформенное требование 64-bit для стандартного генератора.

## 8. Перечень изменений

Производственные файлы:
- composer.json;
- src/Clock/UtcClock.php;
- src/Command/OperationInterface.php, Operation.php, OperationFactory.php, OperationFactoryInterface.php (последний — документация);
- src/Command/Middleware/HandleCommandHandler.php, EventMiddleware.php;
- src/Command/Event/CommandEvent.php, CommandFailedEvent.php;
- src/Command/Factory/OperationFactoryFactory.php (новый), CommandBusFactory.php, HandleCommandHandlerFactory.php, EventMiddlewareFactory.php;
- src/ConfigProvider.php.

OperationResult.php — только документация при необходимости, конструктор и алгоритм без изменений. CommandBus и QueryBus — без изменений логики и сигнатур.

Вспомогательные файлы:
- tests/Fixture/DecoratedOperation.php — тип Identity UUID;
- benchmarks/AttributeBench.php — тип Identity UUID и новый генератор;
- tests/consumer-smoke.php — проверка production install и нового UUID/clock;
- README.md, README.ru.md — обновлённые зависимости, примеры и миграция;
- новые тесты и сценарии PHPBench; проверить все остальные употребления Ramsey, типы идентификаторов и предположения UTC по дереву репозитория.

## 9. Критерии приёмки и TDD

Сначала тест, RED для нового поведения, затем минимальное изменение и GREEN. Проверять публичное поведение, а не private поля. Существующие тесты сохранить, кроме миграции типов и обоснованного изменения контрактов.

### 9.1. Операции и UUID

| ID | Сценарий | Ожидаемый результат |
| --- | --- | --- |
| O01 | create без дополнительных параметров | UUID v7, UTC fallback в интервале до/после вызова, result null |
| O02 | Явный createdAt с не-UTC timezone и микросекундами | Исходная идентичность, timezone, offset, microseconds |
| O03 | Явный id, в том числе alternative UuidInterface | Тот же объект, без конвертации |
| O04 | Явный id и createdAt | Оба значения сохранены |
| O05 | OperationFactory без явно заданных часов | Новый UUID v7, UtcClock |
| O06 | Frozen/sequence clock | Один now на create, исходная дата |
| O07 | Пользовательский UuidFactoryInterface | Один generate на create, сохранение id, нет fromBytes |
| O08 | Много операций при замороженном clock | Разные UUID в проверяемом наборе, независимо от createdAt |
| O09 | Все with/without методы | Сохраняют id и createdAt, не вызывают clock/generate |
| O10 | Невалидные атрибуты | Прежний CQRS InvalidArgumentException, pipeline не запущен |
| O11 | now/generate бросает ошибку | Исходный Throwable; нет вызова downstream |
| O12 | Стандартный UUID | RFC variant, v7, корректная каноническая строка и round trip |
| O13 | Повторный generate стандартной фабрики | Проверить ожидаемую сортировку внутри гарантий Identity; не утверждать глобальную монотонность |

### 9.2. Результаты и события

| ID | Сценарий | Ожидаемый результат |
| --- | --- | --- |
| T01 | OperationResult с явной датой / без | Исходный DateTimeImmutable / UTC fallback |
| T02 | Успешный invoker с управляемыми часами | now после invoker, один раз |
| T03 | Handler возвращает null | OperationResult существует, value null |
| T04 | Завершённая операция | OperationAlreadyCompletedException до locator/invoker/clock |
| T05 | Handler бросает Throwable | Исходная ошибка, часы результата не вызваны |
| T06 | Все три события с пользовательскими часами / по умолчанию | Дата из clock без изменений / UtcClock; Failed сохраняет exception |
| T07 | Первый stop с пользовательскими часами / по умолчанию | Timestamp и isPropagationStopped; дальнейшие слушатели пропущены |
| T08 | Повторный stop() после успешной остановки | Первое время неизменно; часы не читаются повторно |
| T09 | Полный успешный цикл | Отдельные отметки createdAt, Process, processedAt, Processed |
| T10 | Downstream failure | Process и Failed, нет Processed; Failed после сбоя |
| T11 | Clock выдаёт разные timezone или время назад | Нет нормализации, принудительной сортировки и округления |
| T12 | now падает на Process/Result/Failed/Processed | Все границы исключений из § 7, двойная причина сохранена |
| T13 | Suppression слушателей | Не подавляются ошибки создания событий |
| T14 | Отложенная команда | Прежнее значение result и порядок событий |
| T15 | stop() при настроенных часах middleware | Тот же источник времени, читаемый непосредственно в stop(); ошибка часов не изменяет состояние остановки |
| T16 | Явный null во всех конструкторах с ClockInterface | TypeError |
| T17 | UtcClock и стандартные пути при не-UTC timezone PHP | Текущее UTC, новый неизменяемый объект, timezone PHP не меняется |

Использовать управляемые sequence clocks с публичным счётчиком, точные значения и идентичность. Для fallback проверять интервал до/после, не sleep и не точное совпадение двух вызовов системных часов. Не требовать UUID timestamp = createdAt.

### 9.3. DI / consumer

| ID | Сценарий | Ожидаемый результат |
| --- | --- | --- |
| D01 | Только CQRS ConfigProvider | Работает без Clock/Identity ConfigProvider |
| D02 | Зарегистрированы ClockInterface и UuidFactoryInterface | Используются настроенные объекты, корректные даты и id |
| D03 | Реальные Clock/Identity ConfigProvider | Работают с timezone приложения; отдельная интеграционная проверка |
| D04 | get(OperationFactory) и get(OperationFactoryInterface) | Сохранён алиас/общий объект |
| D05 | Пользовательская фабрика/декоратор | Не заменяются; прежний контракт |
| D06 | Отсутствует OperationFactoryInterface в ручном вызове CommandBusFactory | Fallback уважает настройки Clock/UUID |
| D07 | Binding clock или UUID неверного типа/null | InvalidConfigurationException |
| D08 | has/get бросает ошибку | Исходный Throwable |
| D09 | Повторные dispatch с mutable FrozenClock | Новые отметки; один повторно используемый генератор |
| D10 | Production consumer | Установка без dev; успешная команда, запрос, UUID Identity и clock |
| D11 | Прежние ошибки фабрик | Сохранены проверки локаторов, middleware, invoker |

D03 — интеграционный сценарий с реальными версиями Clock/Identity; не добавлять обязательную runtime-зависимость от Clock ради него. D01/D02 обязательны для основного набора Pest.

## 10. Производительность

- Baseline — указанная исходная ревизия с Ramsey, candidate — Identity, одинаковые версии PHP и DI 6.
- Текущий CI сравнивает подменой src при одном vendor. После смены типа UUID это некорректно: старый src требует Ramsey, новые fixtures используют Identity. Для миграции нужны две изолированные рабочие директории со своими Composer dependencies и эквивалентными fixtures на одном runner. Не возвращать Ramsey в производственные зависимости ради сравнения.
- Сохранить существующие 86 сценариев PHPBench и проверку полноты отчёта. Добавить сценарии create default/explicit timestamp+id, OperationFactory без/с clock, reusable/fresh Identity generator, реальный dispatch с событиями/без, stop с часами/без.
- Сравнить время создания/завершения операции, runtime dispatch, стоимость сборки контейнера, пиковую память. Фиксировать PHP, OPcache/JIT, число итераций, warmup и разброс.
- Повторяемое ухудшение горячего сценария более 5% — предлагаемый порог исследования и обоснования, а не заранее установленная гарантия прироста. Не трактовать шум как выигрыш/регрессию.
- Не кешировать timestamp или UUID операции; не обходить invoker, проверки входа или контракт immutable-копий ради скорости.

## 11. План реализации и выпуск

1. Миграция UUID: Composer, типы, Operation::create с готовым id, fixtures, тесты и документация.
2. ClockInterface с UtcClock по умолчанию в OperationFactory и HandleCommandHandler; createdAt и processedAt, TDD.
3. ClockInterface с UtcClock по умолчанию в EventMiddleware и событиях; тесты стадий, stop и исключений.
4. Интеграция DI 6: новая фабрика, алиас, fallback, тесты отсутствующих/невалидных зависимостей и пользовательских фабрик.
5. Перепроверка кода и README на двух языках. Pest, PHPStan max, composer validate --strict, production consumer, сравнительные PHPBench. По возможности mutation analysis; явно отразить непроведённые проверки.
6. До релиза проверить cqrs-app, cqrs-policy, cqrs-retry, cqrs-lock, cqrs-transaction-cycle, cqrs-transport и адаптеры: ограничения Composer, старые UUID-типы, сериализацию, использование id, источники времени. Эта спецификация не подтверждает совместимость или завершение миграции внешних пакетов.
7. После публикации DI 6 повторить установку стабильных зависимостей без временного dev/path override и всю приёмку. Не выпускать CQRS 5 ранее.

При реализации работать от актуального HEAD. Каждая функционально самостоятельная группа — отдельный коммит с тестами. Не смешивать миграцию с посторонним рефакторингом.

## 12. Проверенные источники

- [CQRS — исходная ревизия](https://github.com/componenta/cqrs/tree/35de3ba509292281150414f40e595712432b749f)
- [Identity v1.1.0](https://github.com/componenta/identity/releases/tag/v1.1.0) — минимальный UuidInterface, UuidFactoryInterface и стандартный генератор
- [Componenta UUID — Ramsey compatibility](https://github.com/componenta/uuid/blob/c545bb0e59ffde999e3ac25f318735cc5c203aba/README.md)
- [Clock v1.1.0](https://github.com/componenta/clock/releases/tag/v1.1.0)
- [PSR-20](https://www.php-fig.org/psr/psr-20/)
- [DI 6 — InvokableResolver](https://github.com/componenta/di/blob/2361c398fc938108de67df8ec6a723bf4c4648ff/src/Resolver/Entry/InvokableResolver.php)
- [Текущий workflow CQRS](https://github.com/componenta/cqrs/blob/35de3ba509292281150414f40e595712432b749f/.github/workflows/release.yml)

Контракты создания операций, результатов и событий и их DI-регистрация реализованы. Окончательная приёмка, изолированные сравнительные бенчмарки и проверка внешних CQRS-пакетов остаются обязательными перед релизом.
