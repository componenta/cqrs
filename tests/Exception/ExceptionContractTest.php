<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\Attribute\AsCommandListener;
use Componenta\CQRS\Command\BatchCommandBus;
use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Exception\CommandFailureNotificationException;
use Componenta\CQRS\Command\Exception\HandlerNotFoundException as CommandHandlerNotFoundException;
use Componenta\CQRS\Command\Exception\InvalidCommandMetadataException;
use Componenta\CQRS\Command\Exception\InvalidHandlerException as InvalidCommandHandlerException;
use Componenta\CQRS\Command\Exception\InvalidListenerException;
use Componenta\CQRS\Command\Exception\LocatorExceptionInterface;
use Componenta\CQRS\Command\Exception\OperationAlreadyCompletedException;
use Componenta\CQRS\Command\Exception\UnsupportedCommandException;
use Componenta\CQRS\Command\Factory\HandleCommandHandlerFactory;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationResult;
use Componenta\CQRS\Exception\ExceptionInterface;
use Componenta\CQRS\Exception\InvalidArgumentException as CqrsInvalidArgumentException;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Componenta\CQRS\Factory\CqrsMapsFactory;
use Componenta\CQRS\Map\Exception\CqrsMapConflictException;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\CQRS\Query\Context\Context;
use Componenta\CQRS\Query\Exception\HandlerNotFoundException as QueryHandlerNotFoundException;
use Componenta\CQRS\Query\Exception\InvalidHandlerException as InvalidQueryHandlerException;
use Componenta\CQRS\Query\Exception\UnsupportedQueryException;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\QueryBus;
use Componenta\CQRS\Tests\Fixture\FakeContainer;

it('exposes a common exception boundary while preserving the existing SPL categories', function (Throwable $exception, string $category): void {
    try {
        throw $exception;
    } catch (ExceptionInterface $caught) {
        expect($caught)->toBe($exception)
            ->and($caught)->toBeInstanceOf($category);

        return;
    }

    test()->fail('The CQRS exception was not caught by the common interface.');
})->with([
    'missing command handler' => fn () => [new CommandHandlerNotFoundException('command'), RuntimeException::class],
    'missing query handler' => fn () => [new QueryHandlerNotFoundException(new stdClass()), RuntimeException::class],
    'invalid command handler' => fn () => [new InvalidCommandHandlerException('handler'), LogicException::class],
    'invalid query handler' => fn () => [new InvalidQueryHandlerException('handler'), LogicException::class],
    'invalid listener' => fn () => [new InvalidListenerException('listener'), LogicException::class],
    'unsupported command' => fn () => [new UnsupportedCommandException('command'), InvalidArgumentException::class],
    'unsupported query' => fn () => [new UnsupportedQueryException('query'), InvalidArgumentException::class],
    'invalid metadata' => fn () => [new InvalidCommandMetadataException('metadata'), InvalidArgumentException::class],
    'invalid map' => fn () => [new InvalidCqrsMapException('map'), InvalidArgumentException::class],
    'conflicting map' => fn () => [CqrsMapConflictException::handler('command', 'message'), InvalidArgumentException::class],
    'failure notification' => fn () => [new CommandFailureNotificationException(new RuntimeException('handler'), new RuntimeException('listener')), RuntimeException::class],
    'invalid input' => fn () => [new CqrsInvalidArgumentException('input'), InvalidArgumentException::class],
    'invalid configuration' => fn () => [new InvalidConfigurationException('configuration'), LogicException::class],
    'completed operation' => fn () => [new OperationAlreadyCompletedException('completed'), RuntimeException::class],
]);

it('preserves the existing command locator exception subinterface', function (): void {
    $failure = new CommandHandlerNotFoundException('command');

    expect($failure)->toBeInstanceOf(LocatorExceptionInterface::class)
        ->and($failure)->toBeInstanceOf(ExceptionInterface::class);
});

it('classifies errors generated at public CQRS boundaries as package-owned exceptions', function (string $scenario, string $category): void {
    $command = new stdClass();
    $terminal = new HandleCommandHandler(new CommandHandlerLocator([], new FakeContainer([])));
    $completed = Operation::create($command)->withResult(new OperationResult(null));

    $action = match ($scenario) {
        'command missing handler' => fn () => (new CommandBus($terminal))->dispatch($command),
        'query missing handler' => fn () => (new QueryBus(new HandleQuery(new QueryHandlerLocator([], new FakeContainer([])))))->handle($command),
        'invalid command attributes' => fn () => (new CommandBus($terminal))->dispatch($command, ['' => 'invalid']),
        'invalid query context' => fn () => (new QueryBus(new HandleQuery(new QueryHandlerLocator([], new FakeContainer([])))))->handle($command, ['' => 'invalid']),
        'invalid context update' => fn () => (new Context())->withAttribute('0', true),
        'invalid batch' => fn () => (new BatchCommandBus(new CommandBus($terminal)))->dispatchMany([$command, 42]),
        'invalid middleware list' => fn () => new CommandBus($terminal, [new stdClass()]),
        'invalid listener declaration' => fn () => new AsCommandListener(' '),
        'invalid dependency' => fn () => (new HandleCommandHandlerFactory())(new FakeContainer([CommandHandlerLocatorInterface::class => new stdClass()])),
        'invalid map' => fn () => (new CqrsMapsFactory())(new ContainerValue(new FakeContainer([]), new Config(['cqrs.command_handlers' => null], new Environment([])))),
        'completed operation update' => fn () => $completed->withResult(new OperationResult('again')),
        'completed operation invocation' => fn () => $terminal->handle($completed),
    };

    try {
        $action();
    } catch (ExceptionInterface $exception) {
        expect($exception)->toBeInstanceOf($category);

        return;
    }

    test()->fail('Expected a CQRS-owned exception.');
})->with([
    ['command missing handler', CommandHandlerNotFoundException::class],
    ['query missing handler', QueryHandlerNotFoundException::class],
    ['invalid command attributes', CqrsInvalidArgumentException::class],
    ['invalid query context', CqrsInvalidArgumentException::class],
    ['invalid context update', CqrsInvalidArgumentException::class],
    ['invalid batch', CqrsInvalidArgumentException::class],
    ['invalid middleware list', CqrsInvalidArgumentException::class],
    ['invalid listener declaration', CqrsInvalidArgumentException::class],
    ['invalid dependency', InvalidConfigurationException::class],
    ['invalid map', InvalidCqrsMapException::class],
    ['completed operation update', OperationAlreadyCompletedException::class],
    ['completed operation invocation', OperationAlreadyCompletedException::class],
]);
