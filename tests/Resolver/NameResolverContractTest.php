<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Exception\UnsupportedCommandException;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Command\Resolver\CompositeCommandNameResolver;
use Componenta\CQRS\Query\Exception\UnsupportedQueryException;
use Componenta\CQRS\Query\Resolver\CompositeQueryNameResolver;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;

final class NameResolverContractProbe implements CommandNameResolverInterface, QueryNameResolverInterface
{
    public int $resolved = 0;

    public function __construct(
        private readonly bool $supported,
        private readonly string $name,
        private readonly ?Throwable $failure = null,
    ) {}

    public function supports(object $message): bool
    {
        return $this->supported;
    }

    public function resolve(object $message): string
    {
        ++$this->resolved;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->name;
    }
}

it('rejects an unsupported message without calling any unsupported resolver', function (string $composite, string $exception): void {
    $message = new stdClass();
    $unsupported = new $exception('unsupported message');
    $delegate = new NameResolverContractProbe(false, 'delegate', $unsupported);
    $fallback = new NameResolverContractProbe(false, 'fallback', $unsupported);
    $resolver = new $composite($fallback, $delegate);

    expect($resolver->supports($message))->toBeFalse()
        ->and(fn () => $resolver->resolve($message))->toThrow($exception)
        ->and($delegate->resolved)->toBe(0)
        ->and($fallback->resolved)->toBe(0);
})->with([
    'command' => [CompositeCommandNameResolver::class, UnsupportedCommandException::class],
    'query' => [CompositeQueryNameResolver::class, UnsupportedQueryException::class],
]);

it('selects the first supporting resolver without resolving names during support checks', function (string $composite): void {
    $message = new stdClass();
    $unsupported = new NameResolverContractProbe(false, 'unsupported');
    $first = new NameResolverContractProbe(true, 'first');
    $later = new NameResolverContractProbe(true, 'later');
    $fallback = new NameResolverContractProbe(true, 'fallback');
    $resolver = new $composite($fallback, $unsupported, $first, $later);

    expect($resolver->supports($message))->toBeTrue()
        ->and($first->resolved)->toBe(0)
        ->and($resolver->resolve($message))->toBe('first')
        ->and($first->resolved)->toBe(1)
        ->and($unsupported->resolved)->toBe(0)
        ->and($later->resolved)->toBe(0)
        ->and($fallback->resolved)->toBe(0);
})->with([CompositeCommandNameResolver::class, CompositeQueryNameResolver::class]);

it('propagates a selected resolver failure without falling back', function (string $composite): void {
    $failure = new RuntimeException('name resolution failed');
    $first = new NameResolverContractProbe(true, 'first', $failure);
    $later = new NameResolverContractProbe(true, 'later');
    $fallback = new NameResolverContractProbe(true, 'fallback');
    $resolver = new $composite($fallback, $first, $later);

    try {
        $resolver->resolve(new stdClass());
        test()->fail('Expected the name resolution failure.');
    } catch (RuntimeException $exception) {
        expect($exception)->toBe($failure)
            ->and($first->resolved)->toBe(1)
            ->and($later->resolved)->toBe(0)
            ->and($fallback->resolved)->toBe(0);
    }
})->with([CompositeCommandNameResolver::class, CompositeQueryNameResolver::class]);
