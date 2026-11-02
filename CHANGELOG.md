# poagen/client-runtime Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet — `0.1.0` below is the current tip.

## 0.1.0 - 2026-11-03

### Added

- `Codec` interface: the foreign-type sibling of `ScalarBinding`, for a
  `format` bound to a class this package does not control — one it cannot
  require to implement anything. A property bound through a `Codec` is
  declared as that codec's own `targetType()` directly, never a wrapper, and
  `Marshal\Hydrator`, `Marshal\Marshaller` and `Http\ResponseDecoder` route
  conversion through its `decode()`/`encode()` instead of through a method on
  the value itself. `Marshal\PropertyTypeResolver` reads the codec off the
  emitted `Attribute\Format`'s new `codec` argument, since the property's own
  reflected type can no longer say which binding applies — that used to be
  the wrapper class itself. Covers a nullable, list, and map property, not
  only a bare scalar one (`codec-bindings-for-foreign-value-types`).
- `Format\RamseyUuidCodec` and `Format\SymfonyUuidCodec`: `Codec`
  implementations for `format: uuid`, converting on behalf of `ramsey/uuid`'s
  `UuidInterface` and `symfony/uid`'s `Uuid` respectively — so the emitted
  property is that library type directly, `public UuidInterface $id`, with no
  wrapper and no `->value` hop. Both libraries stay `suggest`, never
  `require` — a client that binds neither pulls in neither. Neither codec
  applies by default; the generator's `Profile::$formats` is what selects
  one. `encode()` pins the canonical RFC 4122 form on both, so the wire shape
  does not follow whichever string cast the consumer's container is
  configured for. Re-expressed from the `ScalarBinding` wrappers
  `Format\RamseyUuid`/`Format\SymfonyUuid` shipped under
  `built-in-uuid-and-binary-format-bindings`
  (`reexpress-the-uuid-bindings-as-codecs.decision-260809`).
- `Format\Base64Codec`: a `Codec` for `format: byte` — base64-encoded bytes
  inside a JSON string — decoding with `base64_decode()` in strict mode and
  encoding with `base64_encode()`, both PHP core. Unlike the two `uuid`
  codecs, this one needs no `Profile::$formats` entry: the generator wires
  it in as a built-in, the same way `date-time` wires in
  `\DateTimeImmutable`, because both directions cost nobody a dependency.
  `decode()` on a payload that is not valid base64 throws naming the format
  rather than returning corrupted bytes (`base64-binding-for-format-byte`).
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
