<?php

declare(strict_types=1);

use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\Command\Middleware\MiddlewareOrder;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;

#[MiddlewareOrder(before: [OrderInnerMiddleware::class])]
final readonly class OrderOuterMiddleware implements MiddlewareInterface
{
    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        return $handler->handle($operation);
    }
}

#[MiddlewareOrder(after: [OrderOuterMiddleware::class])]
final readonly class OrderInnerMiddleware implements MiddlewareInterface
{
    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        return $handler->handle($operation);
    }
}

function middlewareOrderTerminal(): OperationHandlerInterface
{
    return new readonly class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation->withResult(new OperationResult('ok'));
        }
    };
}

it('accepts command middleware satisfying declared ordering constraints', function (): void {
    $bus = new CommandBus(
        middlewareOrderTerminal(),
        [new OrderOuterMiddleware(), new OrderInnerMiddleware()],
    );

    expect($bus->dispatch(new stdClass())->result?->value)->toBe('ok');
});

it('rejects middleware placed after a type it declares in before', function (): void {
    expect(fn() => new CommandBus(
        middlewareOrderTerminal(),
        [new OrderInnerMiddleware(), new OrderOuterMiddleware()],
    ))->toThrow(
        InvalidArgumentException::class,
        'must be registered before',
    );
});

it('ignores ordering targets that are not present in the command bus', function (): void {
    $bus = new CommandBus(
        middlewareOrderTerminal(),
        [new OrderOuterMiddleware()],
    );

    expect($bus->dispatch(new stdClass())->result?->value)->toBe('ok');
});

it('keeps command lifecycle events behind the optional policy boundary', function (): void {
    $attributes = (new ReflectionClass(EventMiddleware::class))
        ->getAttributes(MiddlewareOrder::class);

    expect($attributes)->toHaveCount(1);

    /** @var MiddlewareOrder $order */
    $order = $attributes[0]->newInstance();

    expect($order->after)->toBe([
        Componenta\CQRS\Command\Middleware\PolicyMiddleware::class,
    ])->and($order->before)->toBe([]);
});
