<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;
use Fiber;
use LogicException;
use SplQueue;
use WeakMap;

/**
 * Ensures commands execute sequentially within a single process.
 *
 * When a command handler dispatches another command, it's queued
 * and executed only after the parent command completes.
 *
 * Problem without sequential execution:
 * ```
 * CommandA starts
 *   -> dispatches CommandB (executes immediately, same transaction)
 *   -> dispatches CommandC (executes immediately, same transaction)
 * CommandA finishes
 * // All commands share one transaction - partial failure = inconsistent state
 * ```
 *
 * With sequential execution:
 * ```
 * CommandA starts
 *   -> dispatches CommandB (queued)
 *   -> dispatches CommandC (queued)
 * CommandA finishes (transaction 1 committed)
 * CommandB executes (transaction 2)
 * CommandC executes (transaction 3)
 * ```
 *
 * Note: Nested commands return immediately without a result.
 * If you need the result of a nested command, this is likely
 * a design issue - consider refactoring to avoid this dependency.
 *
 * If any queued command throws an exception, remaining commands
 * in the queue are discarded and the exception propagates.
 *
 * @example
 * ```php
 * $bus = new CommandBus(
 *     new HandleCommandHandler($locator),
 *     new SequentialMiddleware(),
 * );
 * ```
 */
final class SequentialMiddleware implements MiddlewareInterface
{
    private SequentialExecutionState $mainState;

    /** @var WeakMap<object, SequentialExecutionState> */
    private WeakMap $fiberStates;

    public function __construct()
    {
        $this->mainState = new SequentialExecutionState();
        $this->fiberStates = new WeakMap();
    }

    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        $fiber = Fiber::getCurrent();
        $state = $fiber === null
            ? $this->mainState
            : ($this->fiberStates[$fiber] ??= new SequentialExecutionState());
        $state->queue->enqueue([$operation, $handler]);

        if ($state->executing) {
            return $operation;
        }

        $state->executing = true;
        $rootOperation = null;

        try {
            while (!$state->queue->isEmpty()) {
                /** @var array{OperationInterface, OperationHandlerInterface} $item */
                $item = $state->queue->dequeue();
                $result = $item[1]->handle($item[0]);

                $rootOperation ??= $result;
            }
        } finally {
            $state->executing = false;
            $state->queue = new SplQueue();

            if ($fiber !== null) {
                unset($this->fiberStates[$fiber]);
            }
        }

        if ($rootOperation === null) {
            throw new LogicException('Root operation was not set');
        }

        return $rootOperation;
    }
}

/** @internal */
final class SequentialExecutionState
{
    public bool $executing = false;

    /** @var SplQueue<array{OperationInterface, OperationHandlerInterface}> */
    public SplQueue $queue;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }
}
