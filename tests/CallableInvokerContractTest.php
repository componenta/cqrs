<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\DI\CallableInvokerInterface;
use Psr\Container\ContainerInterface;

final readonly class CallableInvokerContractMessage
{
    public function __construct(public string $value) {}
}

final class CallableInvokerContractHandler
{
    public function handle(CallableInvokerContractMessage $message): string
    {
        return 'handled:' . $message->value;
    }
}

final readonly class CallableInvokerContractContainer implements ContainerInterface
{
    public function __construct(private CallableInvokerContractHandler $handler) {}

    public function get(string $id): mixed
    {
        return $this->handler;
    }

    public function has(string $id): bool
    {
        return $id === CallableInvokerContractHandler::class;
    }
}

final class CallableInvokerContractSpy implements CallableInvokerInterface
{
    public int $calls = 0;

    /** @var list<array{callable: mixed, params: array<int|string, mixed>}> */
    public array $received = [];

    public function call(mixed $callable, array $params = []): mixed
    {
        ++$this->calls;
        $this->received[] = ['callable' => $callable, 'params' => $params];

        return 'invoked:' . $callable(...array_values($params));
    }
}

it('dispatches a compiled query handler through the configured callable invoker', function (): void {
    $message = new CallableInvokerContractMessage('query');
    $handler = new CallableInvokerContractHandler();
    $invoker = new CallableInvokerContractSpy();
    $locator = new QueryHandlerLocator(
        [CallableInvokerContractMessage::class => [CallableInvokerContractHandler::class, 'handle', true]],
        container: new CallableInvokerContractContainer($handler),
    );

    $result = (new HandleQuery($locator, $invoker))($message);

    expect($result)->toBe('invoked:handled:query')
        ->and($invoker->calls)->toBe(1)
        ->and($invoker->received[0]['params'])->toBe([$message]);
});

it('dispatches a compiled command handler through the configured callable invoker', function (): void {
    $message = new CallableInvokerContractMessage('command');
    $handler = new CallableInvokerContractHandler();
    $invoker = new CallableInvokerContractSpy();
    $locator = new CommandHandlerLocator(
        [CallableInvokerContractMessage::class => [CallableInvokerContractHandler::class, 'handle', true]],
        container: new CallableInvokerContractContainer($handler),
    );

    $operation = (new HandleCommandHandler($locator, $invoker))->handle(Operation::create($message));

    expect($operation->result?->value)->toBe('invoked:handled:command')
        ->and($invoker->calls)->toBe(1)
        ->and($invoker->received[0]['params'])->toBe([$message]);
});
