# Componenta CQRS

`componenta/cqrs` is the neutral CQRS runtime for PHP 8.4+. It provides command and query buses, immutable command operations, middleware contracts, handler/listener locators, command lifecycle events, metadata access, and the versioned CQRS map consumed by application tooling.

Optional policy, retry, locking, transactions, async transport, workers, and application discovery live in separate packages.

## Installation

```bash
composer require componenta/cqrs
```

## Runtime services

Register `Componenta\CQRS\ConfigProvider`. It provides the standard command/query buses, locators, operation factory, metadata provider, and CQRS map provider.

The main configuration keys are:

```php
Componenta\CQRS\ConfigKey::COMMAND_MIDDLEWARES;
Componenta\CQRS\ConfigKey::QUERY_MIDDLEWARES;
Componenta\CQRS\ConfigKey::CQRS_MAP;
Componenta\CQRS\ConfigKey::COMMAND_METADATA_ATTRIBUTES;
```

Middleware IDs are executed in the order configured. Missing middleware keys mean an empty middleware chain.

## Commands

A command is an object representing a state-changing use case:

```php
$operation = $commands->dispatch(new CreateUserCommand($email));
$result = $operation->result?->value;
```

`CommandBusInterface::dispatch(object $command, array $attributes = [])` creates one `OperationInterface` and sends that operation through the complete configured command pipeline.

The operation contains a UUID v7 operation ID, the command instance, timestamp, operation attributes, and an `OperationResult` after synchronous completion. One command dispatch corresponds to one operation.

### Dispatching multiple commands

`BatchCommandBus` decorates any `CommandBusInterface` and adds explicit sequential multi-dispatch:

```php
$batch = new Componenta\CQRS\Command\BatchCommandBus($commands);

$operations = $batch->dispatchMany([
    new FirstCommand(),
    new SecondCommand(),
]);
```

Every command is dispatched independently through the wrapped bus and returns its own operation. `dispatchMany()` buffers and validates the iterable before dispatching, then preserves command order.

Nested calls to `CommandBusInterface::dispatch()` use normal reentrant dispatch semantics. The core runtime does not defer nested commands behind a hidden sequential middleware. If work must occur after the current command or transaction completes, model that explicitly with events, an outbox, async transport, or a workflow/process manager.

## Command middleware

Command middleware implements:

```php
public function execute(
    OperationInterface $operation,
    OperationHandlerInterface $handler,
): OperationInterface;
```

The core runtime contains `EventMiddleware`; `HandleCommandHandler` is the terminal operation handler and is wired separately by the bus factory.

Middleware order is behavior. Hard cross-package requirements can be declared with `#[MiddlewareOrder(before: [...], after: [...])]`; `CommandBus` validates them before compiling the pipeline, so manual construction and DI-created buses use the same rules. Optional packages use this for invariants such as retry outside transaction and policy before async transport.

## Command lifecycle events

`EventMiddleware` can emit:

- `CommandProcessEvent` before handler execution;
- `CommandProcessedEvent` after successful execution;
- `CommandFailedEvent` after failure before the exception is rethrown.

Listener failures propagate by default. An explicitly constructed `EventMiddleware` may suppress listener-body exceptions only; locator, map, and DI failures still propagate.

## Queries

Queries represent read use cases:

```php
$result = $queries->handle(new GetUserQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` normalizes array context to immutable `Context` before invoking query middleware.

## CQRS map v2

Runtime discovery and compiled execution share one versioned map model. The map contains command handlers/listeners/known commands/metadata and query handlers. Empty sections are omitted; an empty valid artifact is `['version' => 2]`. Serialization is deterministic and conflicting handlers or metadata fail instead of silently overriding each other.

### Environment behavior

Only `APP_ENV=development` may run with an empty base map and live discovery supplied by `componenta/cqrs-app`.

Every other environment value — including `production`, `staging`, and `test` — is compiled-only and requires a current v2 `ConfigKey::CQRS_MAP` artifact. Missing or legacy artifacts fail fast and instruct the operator to rebuild the application cache/map.

Build the production artifact from development discovery:

```bash
APP_ENV=development php bin/console.php app:build
```

## Metadata

`CommandMetadataProviderInterface` exposes command-class metadata through:

```php
$metadata->get($command, Attribute::class);
$metadata->isKnown($command);
```

The default provider is strictly map-backed: metadata absent from the active CQRS map is absent at runtime in every environment. There is no implicit reflection fallback. This keeps development discovery, compiled production, workers, and manually constructed containers on the same metadata contract.

`ReflectionCommandMetadataProvider` remains available as an explicit opt-in implementation for applications that deliberately want reflection-based metadata. Optional packages append their metadata attribute classes through `ConfigKey::COMMAND_METADATA_ATTRIBUTES` so `componenta/cqrs-app` can discover and compile them.

## Discovery attributes

`componenta/cqrs-app` understands the core attributes:

```php
#[Componenta\CQRS\Command\Attribute\AsCommandHandler]
#[Componenta\CQRS\Command\Attribute\AsCommandListener]
#[Componenta\CQRS\Query\Attribute\AsQueryHandler]
```

Handler discovery requires the message in the first parameter slot. Additional required handler parameters are not dependency-injected by the CQRS runtime.

## Optional packages

| Package | Responsibility |
|---|---|
| `componenta/cqrs-app` | Development discovery and build-time CQRS map compilation |
| `componenta/cqrs-policy` | Command/query authorization middleware |
| `componenta/cqrs-retry` | Retry metadata and command retry middleware |
| `componenta/cqrs-lock` | Resource-lock metadata and middleware |
| `componenta/cqrs-transaction-cycle` | Cycle Database transaction middleware |
| `componenta/cqrs-transport` | Async transport contracts, serializers, middleware, and worker |
| `componenta/cqrs-transport-cycle` | Cycle Database transport implementation |
| `componenta/cqrs-transport-console` | Symfony Console worker command |

## Extension points

- `CommandBusInterface` / `QueryBusInterface` for alternate buses or decorators;
- `OperationFactoryInterface` for custom operation construction;
- command/query `MiddlewareInterface` for cross-cutting execution behavior;
- `CommandNameResolverInterface` / `QueryNameResolverInterface` for custom message names;
- locator interfaces for alternate handler/listener resolution;
- `CommandMetadataProviderInterface` for alternate metadata sources;
- `CqrsMapProviderInterface` for alternate immutable map sources.

## Verification

```bash
composer test
composer analyse
```

The package CI verifies PHP 8.4 and 8.5 and runs both the test suite and PHPStan at maximum level.
