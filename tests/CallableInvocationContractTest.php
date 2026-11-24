<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\DI\CallableInvokerInterface;

function cqrsInvocationContractFunction(object $message): string
{
    return 'native';
}

final class CqrsInvocationContractStaticTarget
{
    public static function handle(object $message): string
    {
        return 'native';
    }
}

final class CqrsPrivateStaticTarget implements QueryHandlerLocatorInterface, CallableInvokerInterface
{
    private static function handle(object $query): string
    {
        return 'private-method';
    }

    public function locateFor(object $query): callable
    {
        return self::class . '::handle';
    }

    public function call(mixed $callable, array $params = []): mixed
    {
        return $callable(...$params);
    }
}

it('delegates the located callable and message unchanged to the configured invoker', function (string $target): void {
    $result = new stdClass();
    $locator = new readonly class ($target) implements CommandHandlerLocatorInterface, QueryHandlerLocatorInterface {
        public function __construct(private string $target)
        {
        }

        public function locateFor(object $message): callable
        {
            return $this->target;
        }
    };
    $invoker = new class ($result) implements CallableInvokerInterface {
        public array $calls = [];

        public function __construct(private readonly object $result)
        {
        }

        public function call(mixed $callable, array $params = []): mixed
        {
            $this->calls[] = [$callable, $params];

            return $this->result;
        }
    };
    $query = new stdClass();
    $command = new stdClass();

    $queryResult = (new HandleQuery($locator, $invoker))($query);
    $operation = (new HandleCommandHandler($locator, $invoker))->handle(Operation::create($command));

    expect($queryResult)->toBe($result)
        ->and($operation->result?->value)->toBe($result)
        ->and($invoker->calls)->toBe([
            [$target, [$query]],
            [$target, [$command]],
        ]);
})->with([
    'function name' => ['cqrsInvocationContractFunction'],
    'static method name' => [CqrsInvocationContractStaticTarget::class . '::handle'],
]);

it('preserves private static method access in the custom invoker scope', function (): void {
    $target = new CqrsPrivateStaticTarget();

    expect((new HandleQuery($target, $target))(new stdClass()))->toBe('private-method');
});
