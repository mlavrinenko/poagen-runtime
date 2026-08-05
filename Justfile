set dotenv-load := false

# Same reasoning as `../Justfile`'s identical export: Rector, and PHPStan
# when no `tmpDir` is configured, compile a DI container keyed by config
# hash into $TMPDIR, and the default is a machine-wide /tmp path that
# survives a worktree's own deletion. Scoping it per checkout — here, per
# package root, since this file has to keep working once `runtime/` is
# split off into its own checkout entirely — makes `just clean` able to
# actually clear it.
export TMPDIR := justfile_directory() / "build/cache/tmp"

# List available recipes
default:
    @just --list

# Ensure the per-checkout tool cache exists (see TMPDIR above)
[private]
tmpdir:
    @mkdir -p "{{ TMPDIR }}"

# Run all quality checks (lint + test with the coverage floor enforced)
#
# This is the recipe `git subtree split --prefix=runtime` has to leave
# runnable on its own, with the generator absent
# (`bundle-the-runtime-package-with-its-own-ci-tooling`) — the whole reason
# this file, and every config next to it, exists.
check: tmpdir lint test-coverage

# Full pre-commit gate
gate: check

# Run all linters in parallel
lint: tmpdir
    @echo -n 'cs-fixer phpstan rector linecop jscpd' | parallel --will-cite --halt now,fail=1 --delimiter=' ' just lint-{}

# Check code style (PER-CS 2.0 + PHP 8.4)
#
# Needs `tmpdir` for the same reason `../Justfile`'s `lint-cs-fixer` does:
# php-cs-fixer spawns its parallel workers through PHP's
# `sys_get_temp_dir()`, which is $TMPDIR.
lint-cs-fixer: tmpdir
    php-cs-fixer fix --diff --verbose --dry-run

# Static analysis, same level as the root config (`level: max`) — see
# `phpstan.neon.dist`'s own comment for what this package's config
# deliberately does not carry that the root's does, and why.
lint-phpstan: tmpdir
    phpstan analyse --memory-limit=512M

# Check for Rector refactoring opportunities
lint-rector: tmpdir
    rector --dry-run

# Per-language line-count limits (`quality-gates-at-maximum-strictness`)
lint-linecop:
    linecop

# Duplication gate on this package's own source and tests
#
# One tier, not two: this package emits no code of its own, so it has
# nothing to hold to the separate, per-emitted-tree ceiling the monorepo's
# `lint-jscpd-generated` applies to a generated client — that whole tier
# does not exist here.
#
# 3.0% mirrors `../Justfile`'s `lint-jscpd` RATCHET value rather than being
# independently derived, so the two thresholds read the same even though
# they gate different trees. Measured here on the day this file was
# written: 2.05% duplicated lines, 16 clones over 60 files (`src/` and
# `tests/` together) — comfortably under 3.0%, so there is slack this
# figure could tighten into; re-measure before lowering it rather than
# guessing.
#
# RATCHET: this number only ever moves DOWN. Never raise it to admit a clone.
lint-jscpd:
    jscpd -c .jscpd.json --threshold 3.0 src tests

# Auto-fix code style
#
# Needs `tmpdir` for the same reason `lint-cs-fixer` does.
format: tmpdir
    php-cs-fixer fix --diff --verbose

# Apply Rector refactorings
#
# Needs `tmpdir` for the same reason `lint-rector` does.
format-rector: tmpdir
    rector

# Run tests
test: tmpdir
    phpunit

# Run tests with coverage and enforce the statement-coverage floor
# (coverage-floor-in-the-gate). One floor, not two: this package has only
# one tier, `src/` — the two-floor split in `../Justfile`'s equivalent
# recipe exists because the monorepo measures the generator and this
# package separately from one shared run; standalone, there is only this
# package to measure.
#
# 87.0, not `../Justfile`'s 92.0 for the identical `runtime/src` tier: that
# figure is measured across the WHOLE monorepo suite, which includes
# generator-side integration tests that exercise `runtime/src` too —
# `tests/Integration/ResponseUnionSelectionTest.php` and
# `UnionBranchSelectionTest.php` drive the runtime through the generator's
# own emitted fixtures on purpose (see those files' own comments,
# `bundle-the-runtime-package-with-its-own-ci-tooling`), and Kilbil's
# recorded-response tests exercise the HTTP transport the same way. None of
# that exists once this package is standalone with the generator absent, so
# `runtime/tests` alone measures a lower, but honestly this package's own,
# figure: 87.10% the day this floor was set (621/713 statements) — 87.0 is
# just below it, the same margin `../Justfile`'s own RATCHET comments use
# elsewhere in this monorepo. This is the real baseline this package's own
# tests achieve alone, not a demotion of the root's bar.
#
# Same PCOV wiring as `../Justfile`'s `test-coverage`, for the identical
# reason that recipe's own comment explains: `pcov.directory` must be set
# explicitly or PCOV auto-detects the wrong root and silently reports 0%.
#
# RATCHET: this floor only ever moves UP, as real tests raise the measured
# figure. Lowering it to let a change land is not a fix, it is disabling the
# gate.
test-coverage: tmpdir
    #!/usr/bin/env bash
    set -eo pipefail
    php -d pcov.directory=. -d 'pcov.exclude=~(vendor|build)~' vendor/bin/phpunit --coverage-html build/coverage --coverage-clover build/logs/clover.xml
    scripts/coverage-floor build/logs/clover.xml src=87.0

# Install dependencies
install:
    composer install

# Update dependencies
update:
    composer update

# Clean generated artifacts
clean:
    rm -rf build/cache build/coverage build/logs
