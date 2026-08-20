<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationResult;

it('preserves valid string operation attribute keys', function (): void {
    $operation = Operation::create(new stdClass(), [
        'tenant_id' => 42,
        '__execution_mode' => 'sync',
        '01' => 'leading-zero-string',
    ]);

    expect($operation->attributes)->toBe([
        'tenant_id' => 42,
        '__execution_mode' => 'sync',
        '01' => 'leading-zero-string',
    ]);
});

it('uses createdAt for operation creation time and preserves it across immutable copies', function (): void {
    $operation = Operation::create(new stdClass());
    $createdAt = $operation->createdAt;

    expect($operation->withAttribute('trace_id', 'a')->createdAt)->toBe($createdAt)
        ->and($operation->withAttributes(['trace_id' => 'b'])->createdAt)->toBe($createdAt)
        ->and($operation->withResult(new OperationResult('ok'))->createdAt)->toBe($createdAt);
});

it('rejects integer attribute keys at operation construction boundaries', function (): void {
    expect(fn() => Operation::create(new stdClass(), [0 => 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'non-empty strings')
        ->and(fn() => Operation::create(new stdClass())->withAttributes([1 => 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'non-empty strings');
});

it('rejects numeric-string names that PHP would convert to integer keys', function (): void {
    expect(fn() => Operation::create(new stdClass())->withAttribute('0', 'invalid'))
        ->toThrow(InvalidArgumentException::class, 'converted by PHP to an integer array key')
        ->and(fn() => Operation::create(new stdClass())->withoutAttribute('-1'))
        ->toThrow(InvalidArgumentException::class, 'converted by PHP to an integer array key');
});

it('rejects empty or whitespace-only operation attribute names', function (): void {
    expect(fn() => Operation::create(new stdClass(), ['' => 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'non-empty strings')
        ->and(fn() => Operation::create(new stdClass())->withAttribute('   ', 'invalid'))
        ->toThrow(InvalidArgumentException::class, 'non-empty string');
});
