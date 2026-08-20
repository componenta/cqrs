<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\Middleware\PipelineHandler;
use InvalidArgumentException;

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
}
