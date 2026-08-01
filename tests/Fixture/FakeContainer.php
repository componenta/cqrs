<?php

declare(strict_types=1);

namespace Componenta\CQRS\Tests\Fixture;

use Psr\Container\ContainerInterface;
use RuntimeException;

final readonly class FakeContainer implements ContainerInterface
{
    /** @param array<string, mixed> $entries */
    public function __construct(
        private array $entries = [],
    ) {}

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new RuntimeException(sprintf('Missing container entry: %s', $id));
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
