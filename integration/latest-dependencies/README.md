# Current Componenta dependency integration

This isolated Composer root installs all six callback feature branches:
Interceptor 4, Serialize Interceptor 2, CQRS Lock 4, CQRS Retry 4, HTTP Cache 2,
and Policy 4. Their dependency graph uses Config 4, Reflection 3, DI 6, CQRS 5,
Identity 1.1, Router 3, Middleware Factory 2.1, HTTP Responder 1, Pipeline 1,
MIME Detector 1, Scope 1, HTTP 1, and Symfony Lock 8.1 together on PHP 8.5.13 or newer. It verifies the
combined public installation required by the callable attribute integrations.

Config 4, Reflection 3, Scope 1, and HTTP 1 are released packages. DI 6 and CQRS 5
are development versions. The Identity, Router, Middleware Factory, HTTP
Responder, Pipeline, and MIME Detector support branches extend their existing compatible major lines to
accept the latest dependency contracts. They retain their existing public APIs.

All six callback feature versions are also development branches. This root
explicitly selects their `spec/attribute-callbacks-2026-11-26` sources and the
eight `fix/callback-dependency-baseline-2026-11-26` support branches through
package-scoped Git repositories. These names describe development sources;
they do not imply that the corresponding stable major releases exist.

The Pipeline and MIME Detector branches start from their latest `v1.0.1` sources
and add Config 4 to the supported Config range. They are explicit root requirements:
otherwise Composer can select `v1.0.0` because the released `v1.0.1` manifests
still require Config 1 or 2. The support branches keep their real `1.0.x-dev` identity.

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

This verifies public installation of all fourteen branch packages and the CQRS
consumer contract. Each feature's own suite verifies its callback behavior.
The separate legacy prospective external matrix has a different set of consumers
and is not certified by this graph; see the
[migration note](../../docs/audits/2026-11-26-legacy-external-compatibility.md).

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

The 2026-11-26 integration refresh used these published support commits. The
consumer lock file also records the CQRS checkout and all resolved released packages.

| Package | Verified source commit |
| --- | --- |
| DI | `e4f19ab9a9f6bd18da85fc52c8d478433a32baec` |
| Identity | `3feb94e0d64956ecdf7b22abed4a8bd9f597abeb` |
| Router | `8cc184cd44d14857511653e008cfc0a3ee8b0612` |
| Middleware Factory | `8eb058f32c7ba5939ba20095a5156cdee44d997b` |
| HTTP Responder | `6080a95ad9ebed4a659b8025df4ff0edb2d84add` |
| Pipeline | `1887909eee9aa0ad62d52afe90e686372862af47` |
| MIME Detector | `7bd035263f7ba96e351fbe5cbe3eda19ed7cc135` |

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
