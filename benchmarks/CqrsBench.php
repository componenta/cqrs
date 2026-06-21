<?php

declare(strict_types=1);

namespace Componenta\CQRS\Benchmarks;

use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Attribute\Async;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Metadata\CommandAttributeProviderInterface;
use Componenta\CQRS\Command\Metadata\CompiledCommandAttributeProvider;
use Componenta\CQRS\Command\Metadata\ReflectionCommandAttributeProvider;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Middleware\MiddlewareInterface as CommandMiddlewareInterface;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\App\Compile\CommandAttributeMapCompiler;
use Componenta\CQRS\Query\Context\ContextInterface;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\Middleware\MiddlewareInterface as QueryMiddlewareInterface;
use Componenta\CQRS\Query\QueryBus;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[Iterations(5)]
#[Warmup(2)]
final class CqrsBench
{
    private CommandBus $commandBusWithoutMiddleware;
    private CommandBus $commandBusWithTwoMiddlewares;
    private CommandBus $commandBusWithEightMiddlewares;
    private QueryBus $queryBusWithoutMiddleware;
    private QueryBus $queryBusWithTwoMiddlewares;
    private QueryBus $queryBusWithEightMiddlewares;
    private CommandAttributeProviderInterface $reflectionCommandAttributes;
    private CommandAttributeProviderInterface $compiledCommandAttributes;
    private BenchmarkCommand $command;
    private BenchmarkQuery $query;

    public function setUp(): void
    {
        $commandHandler = new HandleCommandHandler(new BenchmarkCommandHandlerLocator());
        $queryHandler = new HandleQuery(new BenchmarkQueryHandlerLocator());

        $this->commandBusWithoutMiddleware = new CommandBus($commandHandler);
        $this->commandBusWithTwoMiddlewares = new CommandBus(
            $commandHandler,
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
        );
        $this->commandBusWithEightMiddlewares = new CommandBus(
            $commandHandler,
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
            new BenchmarkCommandMiddleware(),
        );

        $this->queryBusWithoutMiddleware = new QueryBus($queryHandler);
        $this->queryBusWithTwoMiddlewares = new QueryBus(
            $queryHandler,
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
        );
        $this->queryBusWithEightMiddlewares = new QueryBus(
            $queryHandler,
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
            new BenchmarkQueryMiddleware(),
        );

        $this->reflectionCommandAttributes = new ReflectionCommandAttributeProvider();
        $this->compiledCommandAttributes = new CompiledCommandAttributeProvider(
            (new CommandAttributeMapCompiler())->compile(
                [BenchmarkAttributedCommand::class],
                [BenchmarkAttributedCommand::class],
            ),
            $this->reflectionCommandAttributes,
        );

        $this->command = new BenchmarkCommand('payload');
        $this->query = new BenchmarkQuery('payload');
    }

    #[Revs(5000)]
    #[Groups(['cqrs', 'command', 'dispatch'])]
    public function benchCommandDispatchWithoutMiddleware(): void
    {
        $this->commandBusWithoutMiddleware->dispatch($this->command);
    }

    #[Revs(5000)]
    #[Groups(['cqrs', 'command', 'dispatch', 'middleware'])]
    public function benchCommandDispatchWithTwoMiddlewares(): void
    {
        $this->commandBusWithTwoMiddlewares->dispatch($this->command);
    }

    #[Revs(5000)]
    #[Groups(['cqrs', 'command', 'dispatch', 'middleware'])]
    public function benchCommandDispatchWithEightMiddlewares(): void
    {
        $this->commandBusWithEightMiddlewares->dispatch($this->command);
    }

    #[Revs(10000)]
    #[Groups(['cqrs', 'query', 'dispatch'])]
    public function benchQueryHandleWithoutMiddleware(): void
    {
        $this->queryBusWithoutMiddleware->handle($this->query);
    }

    #[Revs(10000)]
    #[Groups(['cqrs', 'query', 'dispatch', 'middleware'])]
    public function benchQueryHandleWithTwoMiddlewares(): void
    {
        $this->queryBusWithTwoMiddlewares->handle($this->query);
    }

    #[Revs(10000)]
    #[Groups(['cqrs', 'query', 'dispatch', 'middleware'])]
    public function benchQueryHandleWithEightMiddlewares(): void
    {
        $this->queryBusWithEightMiddlewares->handle($this->query);
    }

    #[Revs(10000)]
    #[Groups(['cqrs', 'command', 'attributes', 'reflection'])]
    public function benchReflectionCommandAttributeLookup(): void
    {
        $this->reflectionCommandAttributes->async(BenchmarkAttributedCommand::class);
    }

    #[Revs(10000)]
    #[Groups(['cqrs', 'command', 'attributes', 'compiled'])]
    public function benchCompiledCommandAttributeLookup(): void
    {
        $this->compiledCommandAttributes->async(BenchmarkAttributedCommand::class);
    }
}

final readonly class BenchmarkCommand
{
    public function __construct(
        public string $payload,
    ) {}
}

#[Async(transport: 'bench', delay: 3)]
final readonly class BenchmarkAttributedCommand
{
    public function __construct(
        public string $payload = 'payload',
    ) {}
}

final readonly class BenchmarkQuery
{
    public function __construct(
        public string $payload,
    ) {}
}

final class BenchmarkCommandHandlerLocator implements CommandHandlerLocatorInterface
{
    public function locateFor(object $command): callable
    {
        return static fn(BenchmarkCommand $command): string => $command->payload;
    }
}

final class BenchmarkQueryHandlerLocator implements QueryHandlerLocatorInterface
{
    public function locateFor(object $query): callable
    {
        return static fn(BenchmarkQuery $query): string => $query->payload;
    }
}

final readonly class BenchmarkCommandMiddleware implements CommandMiddlewareInterface
{
    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        return $handler->handle($operation);
    }
}

final readonly class BenchmarkQueryMiddleware implements QueryMiddlewareInterface
{
    public function handle(object $query, ContextInterface $context, callable $next): mixed
    {
        return $next($query, $context);
    }
}
