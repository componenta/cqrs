# Legacy external compatibility migration status

## Scope

The current callback integration uses six feature branches and eight dependency
support branches through the actual Git repositories and their declared branch
aliases. The [public consumer recipe](../../integration/latest-dependencies/README.md)
verifies that graph. It does **not** certify the separate prospective external
compatibility matrix inherited by this repository.

That matrix remains enabled. No lane has been removed, skipped, or reported as
passing to conceal its dependency conflict. Migrating its application and
transport consumers is separate work from the six callback features.

## Executable entry points

- `.github/workflows/external-compatibility.yml` runs on manual dispatch and
  selected paths in pushes to `main`.
- `.github/workflows/external-quality.yml` runs mutation acceptance on manual
  dispatch and selected compatibility-tool paths in pushes to `main`.
- Both prepare consumers through `tests/compatibility/external-packages.php`
  and the pinned sources in `tests/compatibility/external-packages.json`.

The compatibility targets are `cqrs-app`, `cqrs-app-integration`, `cqrs-policy`,
`cqrs-retry`, `cqrs-lock`, `cqrs-transaction-cycle`, `cqrs-transport`,
`cqrs-transport-console`, and `cqrs-transport-cycle`. Mutation acceptance covers
the same package list except `cqrs-app-integration`. These workflows are separate
from the callback packages' own CI and are not the CQRS release workflow's
`verify` and `consumer` jobs.

## Reproduced dependency failure

Run from the CQRS support checkout on PHP 8.5.13 with Composer and Git available:

```sh
workspace="$(mktemp -d)"
php tests/compatibility/external-packages.php cqrs-lock "$workspace"
```

On 2026-11-26 the unmodified preparation script selected CQRS Lock commit
`dbdfae8f364de5f4f52afa34ade2e5142be1bb9e`. Composer exited with status 2 before
running that consumer's tests: its root requires `componenta/config ^3.0.0`,
while the current CQRS 5 support source requires `componenta/config ^4.0`.
The PHP wrapper then throws for the failed Composer command. This is a resolver
failure, not evidence that those consumer runtime tests passed or failed.

The source inventory also pins DI to
`be3a55f049a8ece7073155eedff241334fdfc83b`, preceding the current DI 6 parameter
and bootstrap contracts. The current verified DI support commit is
`e4f19ab9a9f6bd18da85fc52c8d478433a32baec`. Substituting only that DI pin would
leave the reproduced Config 3/4 conflict in the old consumer.

## Why the old preparation is insufficient

`migrateRequirements()` changes only CQRS and DI constraints in temporary
consumer manifests. It does not migrate Config or Reflection contracts. The
script uses `options.versions` to assign prospective versions such as
`5.0.x-dev` and `6.0.x-dev` to the checked-out sources. Those labels served the
older provisional matrix; they do not prove that the current public branch
manifests install together. The new callback consumer uses actual branch
identities and no version overrides.

The old minimum profile pins Symfony Lock to `7.4.0`; the new callback Lock
feature declares Symfony Lock `^8.1`. Reusing that old minimum unchanged would
test a different supported contract. The transaction and transport targets have
their own minimum profiles, which require separate review when those consumers
migrate. The old PHP 8.6 experimental lane also relaxes PHP upper bounds for
test tooling; it is not the PHP 8.5.13 acceptance evidence for this callback graph.

## Required follow-up

Choose the intended supported major of each external consumer before changing
the matrix. For consumers migrating to Config 4 and DI 6, update their own
declared requirements and verify any changed public contracts. Pin their
published compatible sources, update the DI and other support dependencies,
and replace prospective version overrides with the real branch aliases in the
isolated consumer root. Revisit the minimum dependency profiles for those
chosen majors, then run the consumers' behavior, analysis, negative-control,
and mutation checks. The application integration target must be reviewed as a
complete application graph rather than inferred from the six callback packages.

Until that work is complete, the fourteen-package public callback installation
and its reported package suites are the validated scope. The legacy external
matrix has the reproduced installability blocker described above.
