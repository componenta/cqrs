<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\Middleware\SequentialMiddleware;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;

it('keeps sequential execution state isolated between Fiber contexts', function (): void {
    $middleware = new SequentialMiddleware();
    $firstOperation = Operation::create((object) ['name' => 'first']);
    $secondOperation = Operation::create((object) ['name' => 'second']);
    $firstHandler = new class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            Fiber::suspend('first-suspended');

            return $operation->withResult(new OperationResult('first'));
        }
    };
    $secondHandler = new class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation->withResult(new OperationResult('second'));
        }
    };
    $firstFiber = new Fiber(
        fn(): OperationInterface => $middleware->execute($firstOperation, $firstHandler),
    );
    $secondFiber = new Fiber(
        fn(): OperationInterface => $middleware->execute($secondOperation, $secondHandler),
    );

    expect($firstFiber->start())->toBe('first-suspended');
    $secondFiber->start();
    $secondResult = $secondFiber->getReturn();

    expect($secondResult->result?->value)->toBe('second');

    $firstFiber->resume();

    expect($firstFiber->getReturn()->result?->value)->toBe('first');
});
