<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\Exception\OperationAlreadyCompletedException;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;

final readonly class HandleCommandHandler implements OperationHandlerInterface
{
    public function __construct(
        private CommandHandlerLocatorInterface $locator,
        private CallableInvokerInterface $invoker = new CallableInvoker(),
    ) {
    }

    /**
     * Locates and invokes the command handler, then records its result.
     *
     * @throws OperationAlreadyCompletedException If the operation already has a result.
     * @throws \Componenta\CQRS\Command\Exception\HandlerNotFoundException
     * @throws \Componenta\CQRS\Command\Exception\InvalidHandlerException
     * @throws \Throwable Other failures from lookup, invocation or result creation.
     */
    public function handle(OperationInterface $operation): OperationInterface
    {
        if ($operation->result !== null) {
            throw new OperationAlreadyCompletedException('Operation already has result');
        }

        $handler = $this->locator->locateFor($operation->command);

        return $operation->withResult(
            new OperationResult($this->invoker->call($handler, [$operation->command])),
        );
    }
}
