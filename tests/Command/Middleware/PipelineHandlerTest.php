<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\Middleware\PipelineHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationInterface;

it('compiles command middleware in array iteration order regardless of keys', function (array $keys): void {
    $observed = new ArrayObject();
    $wrap = static fn (string $name): MiddlewareInterface => new readonly class ($observed, $name) implements MiddlewareInterface {
        public function __construct(private ArrayObject $observed, private string $name)
        {
        }

        public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
        {
            $this->observed[] = $this->name . ':before';
            $result = $handler->handle($operation);
            $this->observed[] = $this->name . ':after';

            return $result;
        }
    };
    $terminal = new readonly class ($observed) implements OperationHandlerInterface {
        public function __construct(private ArrayObject $observed)
        {
        }

        public function handle(OperationInterface $operation): OperationInterface
        {
            $this->observed[] = 'terminal';

            return $operation;
        }
    };
    $pipeline = PipelineHandler::compile(
        $terminal,
        array_combine($keys, [$wrap('outer'), $wrap('inner')]),
    );
    $operation = Operation::create(new stdClass());

    expect($pipeline->handle($operation))->toBe($operation)
        ->and($observed->getArrayCopy())->toBe([
            'outer:before',
            'inner:before',
            'terminal',
            'inner:after',
            'outer:after',
        ]);
})->with([
    'list' => [[0, 1]],
    'string keys' => [['z', 'a']],
    'sparse integer keys' => [[2, 8]],
    'reordered integer keys' => [[1, 0]],
]);
