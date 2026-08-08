<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\Exception\CqrsMapConflictException;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\CQRS\Map\Exception\LegacyCqrsMapException;

final readonly class CqrsMap
{
    public const int VERSION = 2;

    /** @var array<string, HandlerDescriptor> */
    private array $commandHandlers;

    /** @var array<string, HandlerDescriptor> */
    private array $queryHandlers;

    /** @var array<string, list<CommandListenerDescriptor>> */
    private array $commandListeners;

    /** @var array<string, array<non-empty-string, CommandMetadataDescriptor>> */
    private array $commandMetadata;

    /** @var array<string, true> */
    private array $knownCommands;

    /**
     * @param array<array-key, mixed> $commandHandlers
     * @param array<array-key, mixed> $queryHandlers
     * @param array<array-key, mixed> $commandListeners
     * @param array<array-key, mixed> $commandMetadata
     * @param array<array-key, mixed> $knownCommands
     */
    public function __construct(
        array $commandHandlers = [],
        array $queryHandlers = [],
        array $commandListeners = [],
        array $commandMetadata = [],
        array $knownCommands = [],
    ) {
        $commandHandlers = self::normalizeHandlers($commandHandlers, 'command');
        $queryHandlers = self::normalizeHandlers($queryHandlers, 'query');
        $commandListeners = self::normalizeListeners($commandListeners);
        $commandMetadata = self::normalizeMetadata($commandMetadata);
        $knownCommands = self::normalizeKnownCommands($knownCommands);

        foreach (array_keys($commandHandlers) as $command) {
            $knownCommands[$command] = true;
        }

        foreach (array_keys($commandListeners) as $command) {
            $knownCommands[$command] = true;
        }

        foreach (array_keys($commandMetadata) as $command) {
            $knownCommands[$command] = true;
        }

        ksort($knownCommands);

        $this->commandHandlers = $commandHandlers;
        $this->queryHandlers = $queryHandlers;
        $this->commandListeners = $commandListeners;
        $this->commandMetadata = $commandMetadata;
        $this->knownCommands = $knownCommands;
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param array<array-key, mixed> $artifact
     */
    public static function fromArray(array $artifact): self
    {
        foreach (ConfigKey::legacyMapKeys() as $legacyKey) {
            if (array_key_exists($legacyKey, $artifact)) {
                throw LegacyCqrsMapException::detected($legacyKey);
            }
        }

        self::assertAllowedKeys($artifact, ['version', 'commands', 'queries'], 'CQRS map');

        if (!array_key_exists('version', $artifact)) {
            throw new InvalidCqrsMapException(
                'CQRS map version is missing. Clear the application cache and run app:build.',
            );
        }

        if (!is_int($artifact['version']) || $artifact['version'] !== self::VERSION) {
            throw new InvalidCqrsMapException(sprintf(
                'Unsupported CQRS map version "%s"; expected %d. Clear the application cache and run app:build.',
                is_scalar($artifact['version']) ? (string) $artifact['version'] : get_debug_type($artifact['version']),
                self::VERSION,
            ));
        }

        $commands = $artifact['commands'] ?? [];
        $queries = $artifact['queries'] ?? [];

        if (!is_array($commands) || !is_array($queries)) {
            throw new InvalidCqrsMapException(
                'CQRS map sections "commands" and "queries" must be arrays.',
            );
        }

        self::assertAllowedKeys(
            $commands,
            ['handlers', 'listeners', 'known', 'metadata'],
            'CQRS commands section',
        );
        self::assertAllowedKeys($queries, ['handlers'], 'CQRS queries section');

        return new self(
            commandHandlers: self::decodeHandlers($commands['handlers'] ?? [], 'command'),
            queryHandlers: self::decodeHandlers($queries['handlers'] ?? [], 'query'),
            commandListeners: self::decodeListeners($commands['listeners'] ?? []),
            commandMetadata: self::decodeMetadata($commands['metadata'] ?? []),
            knownCommands: self::decodeKnownCommands($commands['known'] ?? []),
        );
    }

    public function commandHandler(string $command): ?HandlerDescriptor
    {
        return $this->commandHandlers[$command] ?? null;
    }

    public function queryHandler(string $query): ?HandlerDescriptor
    {
        return $this->queryHandlers[$query] ?? null;
    }

    /**
     * @return list<CommandListenerDescriptor>
     */
    public function commandListeners(string $command): array
    {
        return $this->commandListeners[$command] ?? [];
    }

    /**
     * @param class-string $attribute
     */
    public function commandMetadata(string $command, string $attribute): ?CommandMetadataDescriptor
    {
        return $this->commandMetadata[$command][$attribute] ?? null;
    }

    public function isKnownCommand(string $command): bool
    {
        return isset($this->knownCommands[$command]);
    }

    public function merge(self $other): self
    {
        $commandHandlers = self::mergeHandlers(
            $this->commandHandlers,
            $other->commandHandlers,
            'command',
        );
        $queryHandlers = self::mergeHandlers(
            $this->queryHandlers,
            $other->queryHandlers,
            'query',
        );

        $listeners = $this->commandListeners;

        foreach ($other->commandListeners as $command => $descriptors) {
            foreach ($descriptors as $descriptor) {
                $duplicate = false;

                foreach ($listeners[$command] ?? [] as $existing) {
                    if ($existing->equals($descriptor)) {
                        $duplicate = true;
                        break;
                    }
                }

                if (!$duplicate) {
                    $listeners[$command][] = $descriptor;
                }
            }
        }

        $metadata = $this->commandMetadata;

        foreach ($other->commandMetadata as $command => $descriptors) {
            foreach ($descriptors as $attribute => $descriptor) {
                $existing = $metadata[$command][$attribute] ?? null;

                if ($existing !== null && !$existing->equals($descriptor)) {
                    throw CqrsMapConflictException::metadata($command, $attribute);
                }

                $metadata[$command][$attribute] = $descriptor;
            }
        }

        return new self(
            commandHandlers: $commandHandlers,
            queryHandlers: $queryHandlers,
            commandListeners: $listeners,
            commandMetadata: $metadata,
            knownCommands: $this->knownCommands + $other->knownCommands,
        );
    }

    /**
     * @return array{
     *     version: int,
     *     commands?: array{
     *         handlers?: array<string, array{service: string, method: string}>,
     *         listeners?: array<string, list<array{service: string, events: list<non-empty-string>, priority: int}>>,
     *         known?: array<string, true>,
     *         metadata?: array<string, array<non-empty-string, array{arguments: array<int|string, mixed>}>>,
     *     },
     *     queries?: array{
     *         handlers?: array<string, array{service: string, method: string}>,
     *     },
     * }
     */
    public function toArray(): array
    {
        $commands = [];

        if ($this->commandHandlers !== []) {
            $commands['handlers'] = array_map(
                static fn(HandlerDescriptor $descriptor): array => $descriptor->toArray(),
                $this->commandHandlers,
            );
        }

        if ($this->commandListeners !== []) {
            $commands['listeners'] = array_map(
                static fn(array $descriptors): array => array_map(
                    static fn(CommandListenerDescriptor $descriptor): array => $descriptor->toArray(),
                    $descriptors,
                ),
                $this->commandListeners,
            );
        }

        if ($this->knownCommands !== []) {
            $commands['known'] = $this->knownCommands;
        }

        if ($this->commandMetadata !== []) {
            $commands['metadata'] = array_map(
                static fn(array $descriptors): array => array_map(
                    static fn(CommandMetadataDescriptor $descriptor): array => $descriptor->toArray(),
                    $descriptors,
                ),
                $this->commandMetadata,
            );
        }

        $artifact = ['version' => self::VERSION];

        if ($commands !== []) {
            $artifact['commands'] = $commands;
        }

        if ($this->queryHandlers !== []) {
            $artifact['queries'] = [
                'handlers' => array_map(
                    static fn(HandlerDescriptor $descriptor): array => $descriptor->toArray(),
                    $this->queryHandlers,
                ),
            ];
        }

        return $artifact;
    }

    /**
     * @param array<array-key, mixed> $handlers
     * @return array<string, HandlerDescriptor>
     */
    private static function normalizeHandlers(array $handlers, string $kind): array
    {
        $normalized = [];

        foreach ($handlers as $message => $descriptor) {
            $message = self::assertMessageName($message, $kind);

            if (!$descriptor instanceof HandlerDescriptor) {
                throw new InvalidCqrsMapException(sprintf(
                    'Every %s handler must be a %s instance.',
                    $kind,
                    HandlerDescriptor::class,
                ));
            }

            $normalized[$message] = $descriptor;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $listeners
     * @return array<string, list<CommandListenerDescriptor>>
     */
    private static function normalizeListeners(array $listeners): array
    {
        $normalized = [];

        foreach ($listeners as $command => $descriptors) {
            $command = self::assertMessageName($command, 'command');

            if (!is_array($descriptors) || !array_is_list($descriptors)) {
                throw new InvalidCqrsMapException(
                    'Command listener descriptors must be configured as lists.',
                );
            }

            $normalizedDescriptors = [];
            $seen = [];

            foreach ($descriptors as $index => $descriptor) {
                if (!$descriptor instanceof CommandListenerDescriptor) {
                    throw new InvalidCqrsMapException(sprintf(
                        'Command listener descriptor #%d for "%s" must be a %s instance.',
                        $index,
                        $command,
                        CommandListenerDescriptor::class,
                    ));
                }

                $fingerprint = serialize($descriptor->toArray());

                if (isset($seen[$fingerprint])) {
                    throw new InvalidCqrsMapException(sprintf(
                        'Duplicate command listener descriptor for command "%s" and service "%s".',
                        $command,
                        $descriptor->service,
                    ));
                }

                $seen[$fingerprint] = true;
                $normalizedDescriptors[] = $descriptor;
            }

            if ($normalizedDescriptors !== []) {
                usort($normalizedDescriptors, self::compareListeners(...));
                $normalized[$command] = $normalizedDescriptors;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $metadata
     * @return array<string, array<non-empty-string, CommandMetadataDescriptor>>
     */
    private static function normalizeMetadata(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $command => $descriptors) {
            $command = self::assertMessageName($command, 'command');

            if (!is_array($descriptors)) {
                throw new InvalidCqrsMapException('Command metadata descriptors must be arrays.');
            }

            $normalizedDescriptors = [];

            foreach ($descriptors as $attribute => $descriptor) {
                if (!is_string($attribute)
                    || trim($attribute) === ''
                    || !$descriptor instanceof CommandMetadataDescriptor
                    || $descriptor->attribute !== $attribute
                ) {
                    throw new InvalidCqrsMapException(sprintf(
                        'Invalid command metadata descriptor for command "%s".',
                        $command,
                    ));
                }

                $normalizedDescriptors[$attribute] = $descriptor;
            }

            if ($normalizedDescriptors !== []) {
                ksort($normalizedDescriptors);
                $normalized[$command] = $normalizedDescriptors;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $known
     * @return array<string, true>
     */
    private static function normalizeKnownCommands(array $known): array
    {
        $normalized = [];

        foreach ($known as $command => $value) {
            $command = self::assertMessageName($command, 'command');

            if ($value !== true) {
                throw new InvalidCqrsMapException(
                    'Known CQRS commands must be represented as command-name => true.',
                );
            }

            $normalized[$command] = true;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param mixed $handlers
     * @return array<string, HandlerDescriptor>
     */
    private static function decodeHandlers(mixed $handlers, string $kind): array
    {
        if (!is_array($handlers)) {
            throw new InvalidCqrsMapException(sprintf(
                'CQRS %s handlers section must be an array.',
                $kind,
            ));
        }

        $decoded = [];

        foreach ($handlers as $message => $descriptor) {
            $message = self::assertMessageName($message, $kind);

            if (!is_array($descriptor)) {
                throw new InvalidCqrsMapException(sprintf(
                    'CQRS %s handler descriptor for "%s" must be an array.',
                    $kind,
                    $message,
                ));
            }

            $decoded[$message] = HandlerDescriptor::fromArray($descriptor);
        }

        return $decoded;
    }

    /**
     * @param mixed $listeners
     * @return array<string, list<CommandListenerDescriptor>>
     */
    private static function decodeListeners(mixed $listeners): array
    {
        if (!is_array($listeners)) {
            throw new InvalidCqrsMapException('CQRS command listeners section must be an array.');
        }

        $decoded = [];

        foreach ($listeners as $command => $descriptors) {
            $command = self::assertMessageName($command, 'command');

            if (!is_array($descriptors) || !array_is_list($descriptors)) {
                throw new InvalidCqrsMapException(sprintf(
                    'CQRS command listeners for "%s" must be a list.',
                    $command,
                ));
            }

            $normalizedDescriptors = [];

            foreach ($descriptors as $descriptor) {
                if (!is_array($descriptor)) {
                    throw new InvalidCqrsMapException(sprintf(
                        'CQRS command listener descriptor for "%s" must be an array.',
                        $command,
                    ));
                }

                $normalizedDescriptors[] = CommandListenerDescriptor::fromArray($descriptor);
            }

            if ($normalizedDescriptors !== []) {
                $decoded[$command] = $normalizedDescriptors;
            }
        }

        return $decoded;
    }

    /**
     * @param mixed $metadata
     * @return array<string, array<non-empty-string, CommandMetadataDescriptor>>
     */
    private static function decodeMetadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            throw new InvalidCqrsMapException('CQRS command metadata section must be an array.');
        }

        $decoded = [];

        foreach ($metadata as $command => $descriptors) {
            $command = self::assertMessageName($command, 'command');

            if (!is_array($descriptors)) {
                throw new InvalidCqrsMapException(sprintf(
                    'CQRS command metadata for "%s" must be an array.',
                    $command,
                ));
            }

            $normalizedDescriptors = [];

            foreach ($descriptors as $attribute => $descriptor) {
                if (!is_string($attribute) || trim($attribute) === '' || !is_array($descriptor)) {
                    throw new InvalidCqrsMapException(sprintf(
                        'CQRS command metadata descriptor for "%s" is invalid.',
                        $command,
                    ));
                }

                $normalizedDescriptors[$attribute] = CommandMetadataDescriptor::fromArray(
                    $attribute,
                    $descriptor,
                );
            }

            if ($normalizedDescriptors !== []) {
                $decoded[$command] = $normalizedDescriptors;
            }
        }

        return $decoded;
    }

    /**
     * @param mixed $known
     * @return array<string, true>
     */
    private static function decodeKnownCommands(mixed $known): array
    {
        if (!is_array($known)) {
            throw new InvalidCqrsMapException('CQRS known commands section must be an array.');
        }

        return self::normalizeKnownCommands($known);
    }

    /**
     * @param array<string, HandlerDescriptor> $base
     * @param array<string, HandlerDescriptor> $overlay
     * @return array<string, HandlerDescriptor>
     */
    private static function mergeHandlers(array $base, array $overlay, string $kind): array
    {
        foreach ($overlay as $message => $descriptor) {
            $existing = $base[$message] ?? null;

            if ($existing !== null && !$existing->equals($descriptor)) {
                throw CqrsMapConflictException::handler($kind, $message);
            }

            $base[$message] = $descriptor;
        }

        return $base;
    }

    private static function compareListeners(
        CommandListenerDescriptor $left,
        CommandListenerDescriptor $right,
    ): int {
        return ($right->priority <=> $left->priority)
            ?: ($left->service <=> $right->service)
            ?: (implode("\0", $left->events) <=> implode("\0", $right->events));
    }

    private static function assertMessageName(mixed $message, string $kind): string
    {
        if (!is_string($message) || trim($message) === '') {
            throw new InvalidCqrsMapException(sprintf(
                'Every CQRS %s message name must be a non-empty string.',
                $kind,
            ));
        }

        return $message;
    }

    /**
     * @param array<mixed> $section
     * @param list<string> $allowed
     */
    private static function assertAllowedKeys(array $section, array $allowed, string $name): void
    {
        foreach (array_keys($section) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new InvalidCqrsMapException(sprintf(
                    '%s contains unsupported key "%s".',
                    $name,
                    (string) $key,
                ));
            }
        }
    }
}
