# Componenta CQRS

`componenta/cqrs` is the neutral CQRS runtime for PHP 8.4+. `main` is the CQRS v4 line.

```bash
composer require componenta/cqrs
```

Register `Componenta\CQRS\ConfigProvider`. It provides the standard command/query buses, locators, operation factory, metadata provider, and CQRS map provider.

## Commands and operations

```php
$operation = $commands->dispatch(new CreateUserCommand($email));
$result = $operation->result?->value;
```

One `dispatch()` creates one `OperationInterface` and sends it through the complete command pipeline.

An operation contains:

- UUID v7 `id`;
- the `command` instance;
- `createdAt`, the UTC timestamp when this local operation object was created for dispatch;
- `attributes` with a strict `array<string,mixed>` contract;
- optional `OperationResult`, whose `processedAt` records synchronous completion.

`createdAt` is deliberately not named `startedAt`: operation creation happens before middleware and is not the same thing as handler execution start. Async transports must not restore producer `createdAt` as a worker execution timestamp.

CQRS v4 keeps the operation factory separate from middleware composition:

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

The operation factory is optional; `OperationFactory` is used by default.

### Multiple commands

`BatchCommandBus` is the explicit sequential multi-dispatch decorator:

```php
$batch = new Componenta\CQRS\Command\BatchCommandBus($commands);

$operations = $batch->dispatchMany([
    new FirstCommand(),
    new SecondCommand(),
]);
```

Each command is dispatched independently through the wrapped bus and receives its own operation. Core no longer contains `SequentialMiddleware`; nested `dispatch()` calls use normal reentrant dispatch semantics. Work that must happen after the current command or transaction should use an explicit event, outbox, async transport, or workflow/process manager.

## Command middleware

Command middleware implements:

```php
public function execute(
    OperationInterface $operation,
    OperationHandlerInterface $handler,
): OperationInterface;
```

`HandleCommandHandler` is the terminal handler. `EventMiddleware` provides command lifecycle events.

### Hard ordering constraints

`#[Componenta\CQRS\Command\Middleware\MiddlewareOrder]` exists only for relative order that is a correctness or security invariant. It is not a generic priority system.

```php
#[MiddlewareOrder(
    before: [TransactionMiddleware::class],
    after: [PolicyMiddleware::class],
)]
final class ExampleMiddleware implements MiddlewareInterface
{
}
```

`CommandBus` validates these constraints before compiling the pipeline. Missing target middleware are ignored; when both types are present, an invalid order fails immediately. Manual construction and DI/compiled construction therefore use the same ordering contract.

Current companion packages use hard ordering for cases such as:

- authorization before command side effects and async enqueue;
- async transport before execution-only middleware on the producer side;
- retry outside transaction so every attempt gets its own transaction boundary.

Other relative ordering remains application-defined.

## Command lifecycle events

`EventMiddleware` can emit:

- `CommandProcessEvent` before downstream command execution;
- `CommandProcessedEvent` after success;
- `CommandFailedEvent` after failure before rethrow.

Listener failures propagate by default. When command policy middleware is present, lifecycle events are ordered after authorization.

## Queries

```php
$result = $queries->handle(new GetUserQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` normalizes array context to immutable `Context`. Query context attributes use the same non-empty string-key invariant as operation attributes.

## CQRS map and environments

Runtime discovery and compiled execution use one versioned CQRS map model. An empty valid artifact is:

```php
['version' => 2]
```

Only exact `APP_ENV=development` may use the live discovery overlay from `componenta/cqrs-app`. Every other environment, including `production`, `staging`, and `test`, is compiled-only and requires a current map artifact.

Build it from development discovery:

```bash
APP_ENV=development php bin/console.php app:build
```

## Metadata

`CommandMetadataProviderInterface` exposes:

```php
$metadata->get($command, Attribute::class);
$metadata->isKnown($command);
```

CQRS v4's standard `CompiledCommandMetadataProvider` is strictly map-backed. Missing metadata returns `null`; it never appears implicitly because the command class happens to exist at runtime. This keeps development discovery and compiled execution on the same metadata contract.

`ReflectionCommandMetadataProvider` remains available only as an explicit alternative implementation.

Optional packages add their metadata classes through `ConfigKey::COMMAND_METADATA_ATTRIBUTES`, allowing `componenta/cqrs-app` to discover and compile the same descriptors.

## Discovery attributes

`componenta/cqrs-app` understands:

```php
#[Componenta\CQRS\Command\Attribute\AsCommandHandler]
#[Componenta\CQRS\Command\Attribute\AsCommandListener]
#[Componenta\CQRS\Query\Attribute\AsQueryHandler]
```

A handler's message must occupy the first parameter slot. Additional required handler parameters are not dependency-injected by the CQRS runtime.

## Optional packages

| Package | Responsibility |
|---|---|
| `componenta/cqrs-app` | Development discovery and build-time map compilation |
| `componenta/cqrs-policy` | Command/query authorization |
| `componenta/cqrs-retry` | Retry metadata and middleware |
| `componenta/cqrs-lock` | Resource locking |
| `componenta/cqrs-transaction-cycle` | Cycle Database transactions |
| `componenta/cqrs-transport` | Async transport contracts, serializers, middleware, worker |
| `componenta/cqrs-transport-cycle` | Cycle Database transport |
| `componenta/cqrs-transport-console` | Symfony Console worker |

## Verification

```bash
composer test
composer analyse
composer bench
```

CI targets PHP 8.4/8.5, runs the Pest suite and PHPStan at maximum level, and the ordinary test suite loads benchmark setup as an API compatibility check.
