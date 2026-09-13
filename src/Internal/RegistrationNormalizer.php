<?php

declare(strict_types=1);

namespace Componenta\CQRS\Internal;

use Componenta\Config\Config;
use Componenta\CQRS\Command\Attribute\AsCommandListener;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\Exception\CqrsMapConflictException;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use InvalidArgumentException;

/**
 * @internal
 * @phpstan-type Handler array{service: string, method: string}
 * @phpstan-type Listener array{service: string, events: list<string>, priority: int}
 * @phpstan-type Maps array{command_handlers: array<array-key, Handler>, query_handlers: array<array-key, Handler>, command_listeners: array<array-key, list<Listener>>}
 */
final class RegistrationNormalizer
{
    /** @return Maps */
    public static function fromConfig(Config $config): array
    {
        return self::registrations(
            $config->get(ConfigKey::COMMAND_HANDLERS, []),
            $config->get(ConfigKey::QUERY_HANDLERS, []),
            $config->get(ConfigKey::COMMAND_LISTENERS, []),
        );
    }

    /** @return Maps */
    public static function registrations(mixed $commands = [], mixed $queries = [], mixed $listeners = []): array
    {
        $maps = ['command_handlers' => [], 'query_handlers' => [], 'command_listeners' => []];
        foreach (['command_handlers' => $commands, 'query_handlers' => $queries, 'command_listeners' => $listeners] as $section => $registrations) {
            $context = 'cqrs.' . $section;
            self::assertList($registrations, $context);
            foreach ($registrations as $index => $registration) {
                $label = sprintf('%s[%d]', $context, $index);
                if (!is_array($registration)) {
                    throw new InvalidCqrsMapException($label . ' must be a registration array.');
                }
                $message = self::nonEmptyString($registration['message'] ?? null, $label . '.message');
                unset($registration['message']);
                $label .= ' for "' . $message . '"';
                if ($section === 'command_listeners') {
                    $listener = self::listener($registration, $label);
                    if (in_array($listener, $maps[$section][$message] ?? [], true)) {
                        throw new InvalidCqrsMapException($label . ' repeats an identical listener registration.');
                    }
                    $maps[$section][$message][] = $listener;
                } else {
                    $handler = self::handler($registration, $label);
                    self::addHandler($maps[$section], $message, $handler, $section);
                }
            }
        }
        return self::maps($maps);
    }

    /** @return Maps */
    public static function maps(mixed $maps): array
    {
        self::assertKeys($maps, ['command_handlers', 'query_handlers', 'command_listeners'], 'CQRS maps');
        return [
            'command_handlers' => self::handlers($maps['command_handlers'], 'command_handlers'),
            'query_handlers' => self::handlers($maps['query_handlers'], 'query_handlers'),
            'command_listeners' => self::listeners($maps['command_listeners']),
        ];
    }

    /** @return array<array-key, Handler> */
    public static function handlers(mixed $handlers, string $context): array
    {
        if (!is_array($handlers)) {
            throw new InvalidCqrsMapException($context . ' must be a message-to-handler array.');
        }
        $result = [];
        foreach ($handlers as $message => $handler) {
            $message = self::nonEmptyString((string) $message, $context . ' message');
            $result[$message] = self::handler($handler, $context . ' for "' . $message . '"');
        }
        ksort($result, SORT_STRING);
        return $result;
    }

    /** @return array<array-key, list<Listener>> */
    public static function listeners(mixed $listeners): array
    {
        if (!is_array($listeners)) {
            throw new InvalidCqrsMapException('command_listeners must be a message-to-listeners array.');
        }
        $result = [];
        foreach ($listeners as $message => $registrations) {
            $message = self::nonEmptyString((string) $message, 'command_listeners message');
            $context = 'command_listeners for "' . $message . '"';
            self::assertList($registrations, $context);
            $normalized = [];
            foreach ($registrations as $index => $registration) {
                $listener = self::listener($registration, $context . '[' . $index . ']');
                if (in_array($listener, $normalized, true)) {
                    throw new InvalidCqrsMapException($context . ' repeats an identical listener registration.');
                }
                $normalized[] = $listener;
            }
            usort($normalized, static fn (array $a, array $b): int =>
                ($b['priority'] <=> $a['priority'])
                ?: strcmp($a['service'], $b['service'])
                ?: strcmp(implode("\0", $a['events']), implode("\0", $b['events'])));
            $result[$message] = $normalized;
        }
        ksort($result, SORT_STRING);
        return $result;
    }

    /**
     * @param Maps $source
     * @param Maps $explicit
     * @return Maps
     */
    public static function merge(array $source, array $explicit): array
    {
        foreach (['command_handlers', 'query_handlers'] as $section) {
            foreach ($explicit[$section] as $message => $handler) {
                self::addHandler($source[$section], (string) $message, $handler, $section);
            }
        }
        foreach ($explicit['command_listeners'] as $message => $listeners) {
            foreach ($listeners as $listener) {
                if (!in_array($listener, $source['command_listeners'][$message] ?? [], true)) {
                    $source['command_listeners'][$message][] = $listener;
                }
            }
        }
        return self::maps($source);
    }

    /** @return Handler */
    private static function handler(mixed $handler, string $context): array
    {
        self::assertKeys($handler, ['service', 'method'], $context);
        $service = self::nonEmptyString($handler['service'], $context . '.service');
        $method = self::nonEmptyString($handler['method'], $context . '.method');
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/D', $method)) {
            throw new InvalidCqrsMapException($context . ' has invalid handler method "' . $method . '".');
        }
        return ['service' => $service, 'method' => $method];
    }

    /** @return Listener */
    private static function listener(mixed $listener, string $context): array
    {
        if (!is_array($listener)) {
            throw new InvalidCqrsMapException($context . ' must be a listener array.');
        }
        $listener += ['events' => [], 'priority' => 0];
        self::assertKeys($listener, ['service', 'events', 'priority'], $context);
        $service = self::nonEmptyString($listener['service'], $context . '.service');
        if (!is_int($listener['priority']) || !is_array($listener['events'])) {
            throw new InvalidCqrsMapException($context . ' requires integer priority and list events.');
        }
        try {
            AsCommandListener::assertEventTypes($listener['events']);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidCqrsMapException($context . ': ' . $exception->getMessage(), previous: $exception);
        }
        $events = array_values(array_unique($listener['events']));
        sort($events);
        return ['service' => $service, 'events' => $events, 'priority' => $listener['priority']];
    }

    /**
     * @param array<array-key, Handler> $handlers
     * @param Handler $handler
     */
    private static function addHandler(array &$handlers, string $message, array $handler, string $kind): void
    {
        if (isset($handlers[$message]) && $handlers[$message] !== $handler) {
            throw CqrsMapConflictException::handler($kind, $message);
        }
        $handlers[$message] = $handler;
    }

    /** @return non-empty-string */
    private static function nonEmptyString(mixed $value, string $context): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidCqrsMapException($context . ' must be a non-empty string.');
        }
        return $value;
    }

    /** @phpstan-assert list<mixed> $value */
    private static function assertList(mixed $value, string $context): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidCqrsMapException($context . ' must be a list.');
        }
    }

    /**
     * @param list<string> $keys
     * @phpstan-assert array<string, mixed> $value
     */
    private static function assertKeys(mixed $value, array $keys, string $context): void
    {
        if (!is_array($value)) {
            throw new InvalidCqrsMapException($context . ' must be an array.');
        }
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new InvalidCqrsMapException($context . ' must contain exactly: ' . implode(', ', $keys) . '.');
        }
    }
}
