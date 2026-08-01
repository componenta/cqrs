<?php

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;
use Componenta\DI\Exception\CallableExceptionInterface;

final readonly class HandleCommandHandler implements OperationHandlerInterface
{
    public function __construct(
        private CommandHandlerLocatorInterface $locator,
        private CallableInvokerInterface $invoker = new CallableInvoker()
    ) {
    }

    /**
     * @throws CallableExceptionInterface
     */
    public function handle(OperationInterface $operation): OperationInterface
    {
        $handler = $this->locator->locateFor($operation->command);
        return $operation->withResult(new OperationResult($this->invoker->call($handler, [$operation->command])));
    }
}