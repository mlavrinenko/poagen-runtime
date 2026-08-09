# poagen/client-runtime Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Format\RamseyUuid` and `Format\SymfonyUuid`: `ScalarBinding`
  implementations for `format: uuid`, wrapping `ramsey/uuid` and
  `symfony/uid` respectively. Both libraries are `suggest`, never `require` —
  a client that binds neither pulls in neither. Neither binding applies by
  default; the generator's `Profile::$formats` is what selects one.
  `toScalar()` pins the canonical RFC 4122 form on both, so the wire shape
  does not follow whichever string cast the consumer's container is
  configured for.
- This package's own `composer.json` `require-dev`/`scripts`,
  `phpunit.xml.dist`, `phpstan.neon.dist` (same `level: max` the parent
  monorepo holds the generator to), `rector.php`, `.php-cs-fixer.dist.php`,
  `.jscpd.json`, `.linecop.yaml`, `.editorconfig`, `Justfile`, `.gitignore`
  and this changelog, so that `runtime/` builds, tests and lints with the
  generator absent. `git subtree split --prefix=runtime` of the parent
  repository now produces a tree that installs and gates on its own —
  see `bundle-the-runtime-package-with-its-own-ci-tooling` in the parent
  project's task tracker.
- The test suite exercising this package (`tests/`, namespaced
  `Poagen\Runtime\Tests`) moved here from the parent monorepo's own test
  tree, where it previously ran under `Poagen\Generator\Tests\Unit\Runtime`.

### Note

`src/` predates this changelog: the runtime was developed inside the
[poagen](https://github.com/mlavrinenko/poagen) monorepo from the start
(see that project's own `CHANGELOG.md` for the feature history up to the
point this package became self-contained), and this file tracks changes
from here forward.
