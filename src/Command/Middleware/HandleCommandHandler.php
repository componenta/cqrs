<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Closure;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Operation;
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

    public function handle(OperationInterface $operation): OperationInterface
    {
        // Built-in Operation is single-assignment. Reject completed instances
        // before the handler can execute side effects. Third-party operation
        // implementations retain their own result semantics.
        if ($operation instanceof Operation && $operation->result !== null) {
            throw new \RuntimeException('Operation already has result');
        }

        $handler = $this->locator->locateFor($operation->command);

        // An invoker may treat a string as a container service ID. Preserve the
        // chosen function target, while deferring static-method visibility
        // checks to the invoker's own scope.
        if (is_string($handler)) {
            $handler = str_contains($handler, '::')
                ? explode('::', $handler, 2)
                : Closure::fromCallable($handler);
        }

        return $operation->withResult(
            new OperationResult($this->invoker->call($handler, [$operation->command])),
        );
    }
}
