<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;
use LogicException;
use SplQueue;

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
    private bool $executing = false;

    /** @var SplQueue<array{OperationInterface, OperationHandlerInterface}> */
    private SplQueue $queue;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
    {
        $this->queue->enqueue([$operation, $handler]);

        if ($this->executing) {
            return $operation;
        }

        $this->executing = true;
        $rootOperation = null;

        try {
            while (!$this->queue->isEmpty()) {
                /** @var array{OperationInterface, OperationHandlerInterface} $item */
                $item = $this->queue->dequeue();
                $result = $item[1]->handle($item[0]);

                $rootOperation ??= $result;
            }
        } finally {
            $this->executing = false;
            $this->queue = new SplQueue();
        }

        if ($rootOperation === null) {
            throw new LogicException('Root operation was not set');
        }

        return $rootOperation;
    }
}
