# Current Componenta dependency integration

This isolated Composer root installs Config 4, Reflection 3, DI 6, CQRS 5,
Identity 1.1, Router 3, Middleware Factory 2.1, HTTP Responder 1, Scope 1,
HTTP 1, and Symfony Lock 8.1 together on PHP 8.5.13 or newer. It verifies the
dependency graph required by the callable attribute integrations.

Config 4, Reflection 3, Scope 1, and HTTP 1 are released packages. DI 6 and CQRS 5
are development versions. The Identity, Router, Middleware Factory, and HTTP
Responder support branches extend their existing compatible major lines to
accept the latest dependency contracts. They retain their existing public APIs.

## Run the integration

Check out `fix/callback-dependency-baseline-2026-11-26` in this repository, then
run from the repository root:

```sh
composer validate --strict
composer update --working-dir=integration/latest-dependencies --no-interaction --prefer-dist
composer check-platform-reqs --working-dir=integration/latest-dependencies
php tests/consumer-smoke.php integration/latest-dependencies/vendor/autoload.php
```

The consumer smoke test builds a real Config/DI container and checks CQRS
dispatch, configured services, immutable operation metadata, and result contracts.
It runs against the consumer's production dependencies without the package's
development autoload configuration.

The consumer installs published branch commits and records them in its lock file.
The root explicitly opts into each required development branch. Every support
branch declares its real major through `extra.branch-alias`; the repositories do
not override versions and the root keeps `minimum-stability: stable`. Scope Git VCS
repositories to their named package so unrelated packages continue to resolve
from their normal registry. A normal application can copy these repository and
require entries into its own root while testing the feature branches. Repository
definitions in an installed library are not inherited by a consuming project.

Keep the generated lock file in a consuming application when exact repeatability
is needed. Git transport is used directly so public installation does not depend
on authenticated GitHub API access. Branch names can advance; the lock records the resolved commit for
every package. Once the corresponding releases exist, replace the development
requirements with the real stable major constraints and remove the Git repository entries.
Do not label Config 4 as Config 3 or Reflection 3 as Reflection 2 to satisfy an
older constraint.

## Library manifests and CI

CQRS keeps its production requirements at `componenta/config ^4.0`,
`componenta/reflection ^3.0`, `componenta/di ^6.0`, and
`componenta/identity ^1.1.0`. Application roots opt into development versions;
the library does not lower global stability or publish root-only repositories.

The development CI lane checks out immutable DI and Identity commits and places
each checkout on its declared support branch locally before Composer inspects
the path repository. `.github/prepare-development-dependencies.php` modifies only
the disposable CI/consumer root. Release verification continues to install the
declared stable production requirements, so publishing is blocked until those
dependencies are released.

The support graph was verified on PHP 8.5.13 with each package's test suite and
with the Identity and CQRS production consumer checks. The initial Composer
resolution failed on the old Config 3, DI 5, and Reflection 2 constraints; the
compatibility branches resolve the real Config 4, DI 6, and Reflection 3 packages.
No production source adapters were necessary.
