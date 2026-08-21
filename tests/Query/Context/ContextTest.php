<?php

declare(strict_types=1);

use Componenta\CQRS\Query\Context\Context;

it('is empty by default', function () {
    $ctx = new Context();

    expect($ctx->attributes)->toBe([])
        ->and($ctx->hasAttribute('k'))->toBeFalse()
        ->and($ctx->getAttribute('k'))->toBeNull()
        ->and($ctx->getAttribute('k', 'fallback'))->toBe('fallback');
});

it('returns stored attributes from the constructor', function () {
    $ctx = new Context(['a' => 1, 'b' => 2]);

    expect($ctx->getAttribute('a'))->toBe(1)
        ->and($ctx->getAttribute('b'))->toBe(2)
        ->and($ctx->hasAttribute('a'))->toBeTrue()
        ->and($ctx->hasAttribute('c'))->toBeFalse()
        ->and($ctx->attributes)->toBe(['a' => 1, 'b' => 2]);
});

it('withAttribute returns a new instance with the value set, leaving the original unchanged', function () {
    $original = new Context(['a' => 1]);
    $next = $original->withAttribute('b', 2);

    expect($next)->not->toBe($original)
        ->and($original->attributes)->toBe(['a' => 1])
        ->and($next->attributes)->toBe(['a' => 1, 'b' => 2]);
});

it('returns a stored null instead of substituting the fallback', function () {
    $ctx = new Context(['nullable' => null]);

    expect($ctx->hasAttribute('nullable'))->toBeTrue()
        ->and($ctx->getAttribute('nullable', 'fallback'))->toBeNull();
});

it('withAttribute overrides an existing key', function () {
    $ctx = (new Context(['a' => 1]))->withAttribute('a', 99);

    expect($ctx->getAttribute('a'))->toBe(99);
});

it('withAttributes replaces existing attributes', function () {
    $ctx = (new Context(['a' => 1, 'b' => 2]))->withAttributes(['b' => 20, 'c' => 3]);

    expect($ctx->attributes)->toBe(['b' => 20, 'c' => 3]);
});

it('withoutAttribute removes a key, returning a new instance', function () {
    $original = new Context(['a' => 1, 'b' => 2]);
    $next = $original->withoutAttribute('a');

    expect($next->hasAttribute('a'))->toBeFalse()
        ->and($next->getAttribute('b'))->toBe(2)
        ->and($original->hasAttribute('a'))->toBeTrue();
});

it('withoutAttribute on a missing key returns an equivalent instance', function () {
    $ctx = (new Context(['a' => 1]))->withoutAttribute('zzz');

    expect($ctx->attributes)->toBe(['a' => 1]);
});

it('rejects constructor attributes with non-string or empty names', function (array $attributes): void {
    expect(fn() => new Context($attributes))
        ->toThrow(InvalidArgumentException::class, 'non-empty strings');
})->with([
    'integer key' => [[0 => 'value']],
    'empty string' => [['' => 'value']],
    'whitespace string' => [['   ' => 'value']],
]);

it('rejects names that PHP converts to integer keys in named context operations', function (): void {
    $context = new Context();

    expect(fn() => $context->withAttribute('0', 'value'))
        ->toThrow(InvalidArgumentException::class, 'integer array key')
        ->and(fn() => $context->withoutAttribute('0'))
        ->toThrow(InvalidArgumentException::class, 'integer array key');
});
