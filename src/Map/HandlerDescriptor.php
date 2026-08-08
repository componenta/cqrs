<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;

final readonly class HandlerDescriptor
{
    public function __construct(
        public string $service,
        public string $method,
    ) {
        if (trim($this->service) === '') {
            throw new InvalidCqrsMapException('CQRS handler service id must be a non-empty string.');
        }

        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/D', $this->method)) {
            throw new InvalidCqrsMapException(sprintf(
                'CQRS handler method "%s" is invalid.',
                $this->method,
            ));
        }
    }

    /**
     * @param array<array-key, mixed> $descriptor
     */
    public static function fromArray(array $descriptor): self
    {
        self::assertKeys($descriptor, ['service', 'method']);

        if (!is_string($descriptor['service']) || !is_string($descriptor['method'])) {
            throw new InvalidCqrsMapException(
                'CQRS handler descriptor fields "service" and "method" must be strings.',
            );
        }

        return new self($descriptor['service'], $descriptor['method']);
    }

    /**
     * @return array{service: string, method: string}
     */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'method' => $this->method,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->service === $other->service
            && $this->method === $other->method;
    }

    /**
     * @param array<array-key, mixed> $value
     * @param list<string> $expected
     */
    private static function assertKeys(array $value, array $expected): void
    {
        $keys = array_keys($value);
        sort($keys);
        sort($expected);

        if ($keys !== $expected) {
            throw new InvalidCqrsMapException(
                'CQRS handler descriptor must contain exactly "service" and "method".',
            );
        }
    }
}
