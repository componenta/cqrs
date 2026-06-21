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

it('withAttribute overrides an existing key', function () {
    $ctx = (new Context(['a' => 1]))->withAttribute('a', 99);

    expect($ctx->getAttribute('a'))->toBe(99);
});

it('withAttributes merges on top of existing attributes', function () {
    $ctx = (new Context(['a' => 1, 'b' => 2]))->withAttributes(['b' => 20, 'c' => 3]);

    expect($ctx->attributes)->toBe(['a' => 1, 'b' => 20, 'c' => 3]);
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
