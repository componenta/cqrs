<?php

declare(strict_types=1);

namespace Componenta\CQRS\Benchmarks;

use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;
use Componenta\CQRS\Query\Context\Context;
use DateTimeImmutable;
use DateTimeZone;
use PhpBench\Attributes as Bench;
use Componenta\Identity\UuidFactory;
use Componenta\Identity\UuidInterface;
use stdClass;

#[Bench\BeforeMethods('prepare')]
#[Bench\ParamProviders('provideAttributes')]
#[Bench\Iterations(7)]
#[Bench\Revs(10000)]
#[Bench\Warmup(100)]
final class AttributeBench
{
    private array $attributes;
    private UuidInterface $id;
    private object $command;
    private DateTimeImmutable $createdAt;
    private OperationInterface $operation;
    private OperationResult $result;
    private Context $context;

    public function provideAttributes(): iterable
    {
        foreach ([0, 4, 32, 128] as $size) {
            yield 'attributes-' . $size => ['size' => $size];
        }
    }

    public function prepare(array $params): void
    {
        $this->attributes = [];
        for ($i = 0; $i < $params['size']; ++$i) {
            $this->attributes['key.' . $i] = $i;
        }
        $this->id = (new UuidFactory())->generate();
        $this->command = new stdClass();
        $this->createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->result = new OperationResult('done', $this->createdAt);
        $this->operation = new Operation($this->id, $this->command, $this->createdAt, $this->attributes);
        $this->context = new Context($this->attributes);

        // Verify fixtures outside the timed region.
        if ($this->operation->withResult($this->result)->attributes !== $this->attributes
            || $this->context->withAttribute('extra', 42)->getAttribute('extra') !== 42
            || $this->operation->result !== null) {
            throw new \RuntimeException('Invalid attribute benchmark fixture.');
        }
    }

    public function benchOperationConstruction(array $params): void
    {
        new Operation($this->id, $this->command, $this->createdAt, $this->attributes);
    }

    public function benchOperationCompletion(array $params): void
    {
        $this->operation->withResult($this->result);
    }

    public function benchOperationWithAttribute(array $params): void
    {
        $this->operation->withAttribute('extra', 42);
    }

    public function benchOperationWithoutAttribute(array $params): void
    {
        $this->operation->withoutAttribute('key.0');
    }

    public function benchOperationWithAttributes(array $params): void
    {
        $this->operation->withAttributes($this->attributes);
    }

    public function benchContextConstruction(array $params): void
    {
        new Context($this->attributes);
    }

    public function benchContextWithAttribute(array $params): void
    {
        $this->context->withAttribute('extra', 42);
    }

    public function benchContextWithoutAttribute(array $params): void
    {
        $this->context->withoutAttribute('key.0');
    }

    public function benchContextWithAttributes(array $params): void
    {
        $this->context->withAttributes($this->attributes);
    }
}
