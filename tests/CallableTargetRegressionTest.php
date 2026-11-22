<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\DI\CallableInvokerInterface;

function cqrsTargetIdentityTestFunction(object $message): string
{
    return 'intended';
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

it('preserves a located function target instead of invoking an identically named DI service', function (): void {
    $locator = new class implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable
        {
            return 'cqrsTargetIdentityTestFunction';
        }
    };
    $invoker = new class implements CallableInvokerInterface {
        public function call(mixed $callable, array $params = []): mixed
        {
            // Models an invoker that interprets string callable names as service IDs.
            return is_string($callable) ? 'shadow-service' : $callable(...$params);
        }
    };

    expect((new HandleQuery($locator, $invoker))(new stdClass()))->toBe('intended');
});

it('preserves a located command function target through the terminal', function (): void {
    $locator = new class implements CommandHandlerLocatorInterface {
        public function locateFor(object $command): callable
        {
            return 'cqrsTargetIdentityTestFunction';
        }
    };
    $invoker = new class implements CallableInvokerInterface {
        public function call(mixed $callable, array $params = []): mixed
        {
            return is_string($callable) ? 'shadow-service' : $callable(...$params);
        }
    };
    $result = (new HandleCommandHandler($locator, $invoker))->handle(Operation::create(new stdClass()));

    expect($result->result?->value)->toBe('intended');
});

it('preserves private static method access in the custom invoker scope', function (): void {
    $target = new CqrsPrivateStaticTarget();
    expect((new HandleQuery($target, $target))(new stdClass()))->toBe('private-method');
});
