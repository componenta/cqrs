<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\Middleware\PipelineHandler;

/**
 * Command bus implementation with middleware pipeline support.
 *
 * Dispatches commands through a chain of middlewares, with a terminal handler
 * that executes the actual command. Middlewares can intercept, modify, or
 * decorate command execution (logging, transactions, events, etc.).
 *
 * @example
 * ```php
 * $bus = new CommandBus(
 *     new HandleCommandHandler($handlerLocator),
 *     new EventMiddleware($listenerLocator),
 * );
 *
 * $operation = $bus->dispatch(new CreateUserCommand('john@example.com'));
 * $userId = $operation->result->value;
 * ```
 */
final readonly class CommandBus implements CommandBusInterface
{
    private OperationHandlerInterface $pipeline;

    /**
     * Creates a new command bus.
     *
     * @param OperationHandlerInterface $commandHandler Terminal handler responsible
     *        for locating and invoking the actual command handler. This handler
     *        MUST execute the command and set the operation result.
     * @param MiddlewareInterface ...$middlewares Optional middlewares to wrap
     *        command execution. Executed in order, with last middleware calling
     *        the terminal handler.
     *
     * @see HandleCommandHandler Default terminal handler implementation
     */
    public function __construct(
        OperationHandlerInterface $commandHandler,
        MiddlewareInterface ...$middlewares,
    ) {
        $this->pipeline = PipelineHandler::compile($commandHandler, $middlewares);
    }

    /**
     * Dispatches a command through the middleware pipeline.
     *
     * @param object $command
     * @param array $attributes
     * @return OperationInterface Operation containing the execution result
     */
    public function dispatch(object $command, array $attributes = []): OperationInterface
    {
        return $this->pipeline->handle(Operation::create($command, $attributes));
    }
}

