<?php

declare(strict_types=1);

use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Exception\ExceptionInterface as CqrsExceptionInterface;
use Componenta\CQRS\Command\Exception\HandlerNotFoundException as CommandHandlerNotFoundException;
use Componenta\CQRS\Command\Exception\InvalidHandlerException as InvalidCommandHandlerException;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Middleware\MiddlewareInterface as CommandMiddleware;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\NamedCommandInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Query\Context\ContextInterface;
use Componenta\CQRS\Query\Exception\HandlerNotFoundException as QueryHandlerNotFoundException;
use Componenta\CQRS\Query\Exception\InvalidHandlerException as InvalidQueryHandlerException;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\Middleware\MiddlewareInterface as QueryMiddleware;
use Componenta\CQRS\Query\NamedQueryInterface;
use Componenta\CQRS\Query\QueryBus;
use Componenta\CQRS\Tests\Fixture\FakeContainer;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;
use Componenta\DI\Exception\ExceptionInterface as DiExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final readonly class BusFailureContractMessage implements NamedCommandInterface, NamedQueryInterface
{
    public function __construct(
        public string $commandName = 'unregistered.command',
        public string $queryName = 'unregistered.query',
    ) {}
}

function invokeBusFailureContract(
    string $kind,
    object $message,
    array $handlers,
    ContainerInterface $container,
    array $attributes = [],
    ?CallableInvokerInterface $invoker = null,
): mixed {
    $invoker ??= new CallableInvoker();

    return match ($kind) {
        'command' => (new CommandBus(new HandleCommandHandler(
            new CommandHandlerLocator($handlers, $container),
            $invoker,
        )))->dispatch($message, $attributes),
        'query' => (new QueryBus(new HandleQuery(
            new QueryHandlerLocator($handlers, $container),
            $invoker,
        )))->handle($message, $attributes),
    };
}

it('reports missing handler registration with the resolved message name', function (string $kind, string $expected, string $name): void {
    expect(fn () => invokeBusFailureContract($kind, new BusFailureContractMessage(), [], new FakeContainer([])))
        ->toThrow($expected, $name);
})->with([
    'command' => ['command', CommandHandlerNotFoundException::class, 'unregistered.command'],
    'query' => ['query', QueryHandlerNotFoundException::class, 'unregistered.query'],
]);

it('distinguishes invalid registered handlers from missing registrations', function (string $kind, string $method, string $expected): void {
    expect(fn () => invokeBusFailureContract(
        $kind,
        new stdClass(),
        [stdClass::class => ['service' => 'handler', 'method' => $method]],
        new FakeContainer(['handler' => new stdClass()]),
    ))->toThrow($expected);
})->with([
    'command non-invokable' => ['command', '__invoke', InvalidCommandHandlerException::class],
    'query non-invokable' => ['query', '__invoke', InvalidQueryHandlerException::class],
    'command absent method' => ['command', 'missing', InvalidCommandHandlerException::class],
    'query absent method' => ['query', 'missing', InvalidQueryHandlerException::class],
]);

it('propagates the original exception when resolving a registered service fails', function (string $kind, bool $missing): void {
    $failure = $missing
        ? new class ('service is missing') extends RuntimeException implements NotFoundExceptionInterface {}
        : new class ('service creation failed') extends RuntimeException implements ContainerExceptionInterface {};

    $container = new readonly class ($failure) implements ContainerInterface {
        public function __construct(private Throwable $failure) {}

        public function get(string $id): mixed
        {
            throw $this->failure;
        }

        public function has(string $id): bool
        {
            return !$this->failure instanceof NotFoundExceptionInterface;
        }
    };

    try {
        invokeBusFailureContract(
            $kind,
            new stdClass(),
            [stdClass::class => ['service' => 'handler', 'method' => '__invoke']],
            $container,
        );
    } catch (Throwable $exception) {
        expect($exception)->toBe($failure)
            ->and($exception)->not->toBeInstanceOf(CqrsExceptionInterface::class);

        return;
    }

    test()->fail('Expected the container exception.');
})->with([
    'command missing service' => ['command', true],
    'query missing service' => ['query', true],
    'command service creation failure' => ['command', false],
    'query service creation failure' => ['query', false],
]);

it('propagates an invoker preparation failure without wrapping it or executing the handler', function (string $kind): void {
    $failure = new class ('invocation preparation failed') extends RuntimeException implements DiExceptionInterface {};
    $calls = new ArrayObject();
    $handler = static function (object $message) use ($calls): void {
        $calls[] = $message;
    };
    $invoker = new readonly class ($failure) implements CallableInvokerInterface {
        public function __construct(private Throwable $failure) {}

        public function call(mixed $callable, array $params = []): mixed
        {
            throw $this->failure;
        }
    };

    try {
        invokeBusFailureContract(
            $kind,
            new stdClass(),
            [stdClass::class => ['service' => 'handler', 'method' => '__invoke']],
            new FakeContainer(['handler' => $handler]),
            invoker: $invoker,
        );
    } catch (Throwable $exception) {
        expect($exception)->toBe($failure)
            ->and($exception)->not->toBeInstanceOf(CqrsExceptionInterface::class)
            ->and($calls->getArrayCopy())->toBe([]);

        return;
    }

    test()->fail('Expected the invoker exception.');
})->with(['command', 'query']);

it('propagates the original domain exception from the executed handler', function (string $kind): void {
    $failure = new DomainException('domain rule violated');

    try {
        invokeBusFailureContract(
            $kind,
            new stdClass(),
            [stdClass::class => ['service' => 'handler', 'method' => '__invoke']],
            new FakeContainer([
                'handler' => static fn (object $message): never => throw $failure,
            ]),
        );
    } catch (Throwable $exception) {
        expect($exception)->toBe($failure)
            ->and($exception)->not->toBeInstanceOf(CqrsExceptionInterface::class);

        return;
    }

    test()->fail('Expected the domain exception.');
})->with(['command', 'query']);

it('rejects invalid dispatch attribute names before reaching handler lookup', function (string $kind, array $attributes): void {
    expect(fn () => invokeBusFailureContract(
        $kind,
        new stdClass(),
        [],
        new FakeContainer([]),
        $attributes,
    ))->toThrow(InvalidArgumentException::class);
})->with(['command', 'query'])->with([
    'empty' => [['' => 'value']],
    'whitespace' => [[' ' => 'value']],
    'integer' => [[0 => 'value']],
]);

it('distinguishes a synchronous command returning null from a command deferred by middleware', function (): void {
    $message = new stdClass();
    $completed = invokeBusFailureContract(
        'command',
        $message,
        [stdClass::class => ['service' => 'handler', 'method' => '__invoke']],
        new FakeContainer(['handler' => static fn (object $message): mixed => null]),
    );
    $middleware = new readonly class implements CommandMiddleware {
        public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
        {
            return $operation;
        }
    };
    $deferred = (new CommandBus(
        new HandleCommandHandler(new CommandHandlerLocator([], new FakeContainer([]))),
        [$middleware],
    ))->dispatch($message);

    expect($completed->result)->not->toBeNull()
        ->and($completed->result->value)->toBeNull()
        ->and($deferred->result)->toBeNull();
});

it('allows a query middleware to return null without reaching handler lookup', function (): void {
    $middleware = new readonly class implements QueryMiddleware {
        public function handle(object $query, ContextInterface $context, callable $next): mixed
        {
            return null;
        }
    };
    $bus = new QueryBus(
        new HandleQuery(new QueryHandlerLocator([], new FakeContainer([]))),
        $middleware,
    );

    expect($bus->handle(new stdClass()))->toBeNull();
});
