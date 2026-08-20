<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\Command\Middleware\MiddlewareOrder;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\Middleware\PipelineHandler;
use InvalidArgumentException;
use ReflectionClass;

/** Command bus implementation with middleware pipeline support. */
final readonly class CommandBus implements CommandBusInterface
{
    private OperationHandlerInterface $pipeline;
    private OperationFactoryInterface $operationFactory;

    /**
     * @param OperationHandlerInterface $commandHandler Terminal command operation handler.
     * @param list<MiddlewareInterface> $middlewares Middleware in execution order.
     * @param OperationFactoryInterface|null $operationFactory Custom operation factory.
     */
    public function __construct(
        OperationHandlerInterface $commandHandler,
        array $middlewares = [],
        ?OperationFactoryInterface $operationFactory = null,
    ) {
        if (!array_is_list($middlewares)) {
            throw new InvalidArgumentException(
                'Command middleware collection must be a list.',
            );
        }

        foreach ($middlewares as $index => $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Command middleware at index %d must implement %s; got %s.',
                    $index,
                    MiddlewareInterface::class,
                    get_debug_type($middleware),
                ));
            }
        }

        self::assertMiddlewareOrder($middlewares);

        $this->operationFactory = $operationFactory ?? new OperationFactory();
        $this->pipeline = PipelineHandler::compile($commandHandler, $middlewares);
    }

    /** @param array<string, mixed> $attributes */
    public function dispatch(object $command, array $attributes = []): OperationInterface
    {
        return $this->pipeline->handle(
            $this->operationFactory->create($command, $attributes),
        );
    }

    /** @param list<MiddlewareInterface> $middlewares */
    private static function assertMiddlewareOrder(array $middlewares): void
    {
        foreach ($middlewares as $index => $middleware) {
            $attributes = (new ReflectionClass($middleware))->getAttributes(MiddlewareOrder::class);

            if ($attributes === []) {
                continue;
            }

            /** @var MiddlewareOrder $order */
            $order = $attributes[0]->newInstance();

            foreach ($order->before as $target) {
                self::assertBefore($middlewares, $index, $middleware, $target);
            }

            foreach ($order->after as $target) {
                self::assertAfter($middlewares, $index, $middleware, $target);
            }
        }
    }

    /**
     * @param list<MiddlewareInterface> $middlewares
     * @param class-string $target
     */
    private static function assertBefore(
        array $middlewares,
        int $index,
        MiddlewareInterface $middleware,
        string $target,
    ): void {
        foreach ($middlewares as $targetIndex => $candidate) {
            if ($targetIndex === $index || !is_a($candidate, $target)) {
                continue;
            }

            if ($index > $targetIndex) {
                throw new InvalidArgumentException(sprintf(
                    'Command middleware "%s" must be registered before "%s".',
                    $middleware::class,
                    $candidate::class,
                ));
            }
        }
    }

    /**
     * @param list<MiddlewareInterface> $middlewares
     * @param class-string $target
     */
    private static function assertAfter(
        array $middlewares,
        int $index,
        MiddlewareInterface $middleware,
        string $target,
    ): void {
        foreach ($middlewares as $targetIndex => $candidate) {
            if ($targetIndex === $index || !is_a($candidate, $target)) {
                continue;
            }

            if ($index < $targetIndex) {
                throw new InvalidArgumentException(sprintf(
                    'Command middleware "%s" must be registered after "%s".',
                    $middleware::class,
                    $candidate::class,
                ));
            }
        }
    }
}
