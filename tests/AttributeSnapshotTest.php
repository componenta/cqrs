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


it('preserves attribute order and shallow value identity across completed operation copies', function (): void {
    $value = 'original';
    $nested = 'nested';
    $object = new stdClass();
    $attributes = ['ref' => &$value, 'object' => $object, 'nested' => ['ref' => &$nested], 'null' => null];
    $command = new stdClass();
    $original = Operation::create($command, $attributes);
    $result = new \Componenta\CQRS\Command\OperationResult(null);
    $completed = $original->withResult($result);
    $changed = $completed->withAttribute('ref', 'replacement');
    $removed = $changed->withoutAttribute('null');
    $value = 'changed';
    $nested = 'shared';

    expect($completed)->not->toBe($original)
        ->and($completed->id)->toBe($original->id)
        ->and($completed->createdAt)->toBe($original->createdAt)
        ->and($completed->command)->toBe($command)
        ->and($original->result)->toBeNull()
        ->and($completed->result)->toBe($result)
        ->and($changed->result)->toBe($result)
        ->and($removed->result)->toBe($result)
        ->and(array_keys($changed->attributes))->toBe(['ref', 'object', 'nested', 'null'])
        ->and($completed->attributes)->toBe(['ref' => 'original', 'object' => $object, 'nested' => ['ref' => 'shared'], 'null' => null])
        ->and($removed->attributes)->toBe(['ref' => 'replacement', 'object' => $object, 'nested' => ['ref' => 'shared']])
        ->and($removed->withoutAttribute('absent'))->not->toBe($removed);
});

it('retains shallow context semantics and creates distinct instances for no-op copies', function (): void {
    $value = 'original';
    $object = new stdClass();
    $context = new Context(['key' => &$value, 'object' => $object, 'null' => null]);
    $next = $context->withAttribute('key', 'replacement');
    $without = $context->withoutAttribute('absent');
    $value = 'changed';

    expect($next->attributes)->toBe(['key' => 'replacement', 'object' => $object, 'null' => null])
        ->and($context->attributes)->toBe(['key' => 'original', 'object' => $object, 'null' => null])
        ->and($without)->not->toBe($context)
        ->and($without->attributes)->toBe($context->attributes)
        ->and($context->withAttributes($context->attributes))->not->toBe($context);
});
