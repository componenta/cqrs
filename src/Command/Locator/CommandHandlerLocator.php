<?php

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Exception\HandlerNotFoundException;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Psr\Container\ContainerInterface;

/**
 * Plain command-handler locator backed by a flat map.
 *
 * Mirrors {@see \Componenta\CQRS\Query\Locator\QueryHandlerLocator}: entries are
 * either ready-to-use callables or `[class-string, method-string]` pairs
 * that get lazily resolved through the container the first time the
 * command is dispatched.
 */
final class CommandHandlerLocator implements CommandHandlerLocatorInterface, CommandSupportAwareInterface
{
   use CommandNameResolution;

    /**
     * @param array<string, callable|array{0: class-string, 1: string}> $map
     */
    public function __construct(
        private array $map = [],
        ?CommandNameResolverInterface $resolver = null,
        private readonly ?ContainerInterface $container = null,
    ) {
        $this->resolver = $resolver;
    }

    public function register(string $commandName, callable $handler): void
    {
        $this->map[$commandName] = $handler;
    }

    /**
     * @template T of object
     * @param T $command
     * @return callable(T): mixed
     *
     * @throws HandlerNotFoundException
     */
    public function locateFor(object $command): callable
    {
        $commandName = $this->resolveCommandName($command);

        if (!isset($this->map[$commandName])) {
            throw new HandlerNotFoundException($commandName);
        }

        $entry = $this->map[$commandName];

        if (is_callable($entry)) {
            return $entry;
        }

        if (is_array($entry) && isset($entry[0], $entry[1]) && is_string($entry[0]) && is_string($entry[1])) {
            if ($this->container === null) {
                throw new \LogicException(sprintf(
                    'CommandHandlerLocator: cannot resolve handler "%s::%s" for "%s" - '
                    . 'no container was supplied. Pass one in the constructor when '
                    . 'using compiled `[class, method]` pairs.',
                    $entry[0], $entry[1], $commandName,
                ));
            }

            $handler = $this->container->get($entry[0]);
            $callable = $entry[1] === '__invoke' ? $handler : $handler->{$entry[1]}(...);
            $this->map[$commandName] = $callable;

            return $callable;
        }

        throw new \LogicException(sprintf(
            'CommandHandlerLocator: handler entry for "%s" must be callable or '
            . '[class-string, method-string]; got %s.',
            $commandName,
            get_debug_type($entry),
        ));
    }

    public function supports(object $command): bool
    {
        return isset($this->map[$this->resolveCommandName($command)]);
    }
}