<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Query\Context\Context;

it('isolates context attributes from caller references and returned array copies', function (): void {
    $tenant = 'original';
    $attributes = ['tenant' => &$tenant];
    $context = new Context($attributes);

    $tenant = 'changed';
    expect($context->getAttribute('tenant'))->toBe('original');

    $copy = $context->attributes;
    $copy['tenant'] = 'copied';
    expect($context->getAttribute('tenant'))->toBe('original');
});

it('isolates operation attributes from caller references and returned array copies', function (): void {
    $tenant = 'original';
    $attributes = ['tenant' => &$tenant];
    $operation = Operation::create(new stdClass(), $attributes);

    $tenant = 'changed';
    expect($operation->attributes['tenant'])->toBe('original');

    $copy = $operation->attributes;
    $copy['tenant'] = 'copied';
    expect($operation->attributes['tenant'])->toBe('original');
});

it('isolates attributes in derived context and operation instances', function (): void {
    $tenant = 'original';
    $original = new Context(['tenant' => &$tenant]);
    $context = $original->withAttribute('extra', 1);
    $operation = Operation::create(new stdClass(), ['tenant' => &$tenant])->withAttributes(['tenant' => &$tenant]);

    $tenant = 'changed';
    expect($original->getAttribute('tenant'))->toBe('original')
        ->and($context->getAttribute('tenant'))->toBe('original')
        ->and($operation->attributes['tenant'])->toBe('original');
});

it('preserves identity of object attribute values without deep-cloning them', function (): void {
    $object = new stdClass();
    $context = new Context(['object' => $object]);
    $operation = Operation::create(new stdClass(), ['object' => $object]);

    expect($context->getAttribute('object'))->toBe($object)
        ->and($operation->attributes['object'])->toBe($object);
});
