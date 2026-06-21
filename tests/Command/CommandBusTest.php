<?php

declare(strict_types=1);

use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;

final readonly class CommandBusContractCommand
{
    public function __construct(public string $tag) {}
}

function makeCommandBusTerminal(?ArrayObject $observed = null): OperationHandlerInterface
{
    return new readonly class($observed) implements OperationHandlerInterface {
        public function __construct(private ?ArrayObject $observed) {}

        public function handle(OperationInterface $operation): OperationInterface
        {
            $this->observed?->append('handler');

            return $operation->withResult(new OperationResult('handled:' . $operation->command->tag));
        }
    };
}

function makeCommandBusCommand(string $tag): object
{
    return new readonly class($tag) {
        public function __construct(public string $tag) {}
    };
}

it('dispatches a command without middleware and returns operation result', function () {
    $bus = new CommandBus(makeCommandBusTerminal());

    $operation = $bus->dispatch(makeCommandBusCommand('plain'));

    expect($operation->result?->value)->toBe('handled:plain');
});

it('preserves dispatch attributes and result through the command pipeline', function () {
    $command = makeCommandBusCommand('attributes');
    $bus = new CommandBus(makeCommandBusTerminal());

    $operation = $bus->dispatch($command, ['trace_id' => 'trace-1']);

    expect($operation->command)->toBe($command)
        ->and($operation->attributes)->toBe(['trace_id' => 'trace-1'])
        ->and($operation->result?->value)->toBe('handled:attributes');
});

it('chains command middlewares in registration order, onion-style', function () {
    $observed = new ArrayObject();

    $makeMiddleware = static function (string $tag) use ($observed): MiddlewareInterface {
        return new readonly class($observed, $tag) implements MiddlewareInterface {
            public function __construct(private ArrayObject $observed, private string $tag) {}

            public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
            {
                $this->observed[] = "{$this->tag}-before";
                $result = $handler->handle($operation);
                $this->observed[] = "{$this->tag}-after";

                return $result;
            }
        };
    };

    $bus = new CommandBus(makeCommandBusTerminal($observed), $makeMiddleware('mw1'), $makeMiddleware('mw2'));

    $bus->dispatch(makeCommandBusCommand('ordered'));

    expect($observed->getArrayCopy())->toBe(['mw1-before', 'mw2-before', 'handler', 'mw2-after', 'mw1-after']);
});

it('allows command middleware to short-circuit the terminal handler', function () {
    $observed = new ArrayObject();

    $middleware = new readonly class($observed) implements MiddlewareInterface {
        public function __construct(private ArrayObject $observed) {}

        public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface
        {
            $this->observed[] = 'middleware';

            return $operation->withResult(new OperationResult('short-circuited'));
        }
    };

    $bus = new CommandBus(makeCommandBusTerminal($observed), $middleware);

    $operation = $bus->dispatch(makeCommandBusCommand('short'));

    expect($operation->result?->value)->toBe('short-circuited')
        ->and($observed->getArrayCopy())->toBe(['middleware']);
});

it('propagates exceptions from the terminal command handler', function () {
    $terminal = new readonly class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            throw new RuntimeException('command failed');
        }
    };
    $bus = new CommandBus($terminal);

    expect(fn () => $bus->dispatch(makeCommandBusCommand('boom')))
        ->toThrow(RuntimeException::class, 'command failed');
});

it('dispatches command listeners in priority order', function () {
    $observed = new ArrayObject();
    $locator = new CommandListenersLocator();
    $makeListener = static function (string $name) use ($observed): CommandListenerInterface {
        return new readonly class($observed, $name) implements CommandListenerInterface {
            public function __construct(private ArrayObject $observed, private string $name) {}

            public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
            {
                $this->observed[] = $this->name . ':' . $event::class;
            }
        };
    };

    $locator->register(
        CommandBusContractCommand::class,
        $makeListener('low'),
        [CommandProcessEvent::class],
        priority: -10,
    );
    $locator->register(
        CommandBusContractCommand::class,
        $makeListener('high'),
        [CommandProcessEvent::class],
        priority: 50,
    );

    $bus = new CommandBus(makeCommandBusTerminal(), new EventMiddleware($locator, suppressExceptions: false));

    $bus->dispatch(new CommandBusContractCommand('listeners'));

    expect($observed->getArrayCopy())->toBe([
        'high:' . CommandProcessEvent::class,
        'low:' . CommandProcessEvent::class,
    ]);
});
