<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

// Scoped to `runtime/` alone, with paths relative to this file rather than
// the monorepo root (`bundle-the-runtime-package-with-its-own-ci-tooling`):
// `git subtree split --prefix=runtime` has to produce a tree where this file
// still resolves every path it names, and an `__DIR__ . '/../src'` reaching
// up out of `runtime/` would not survive that split. The set list mirrors
// `../rector.php`'s exactly — same PHP-8.4 level set and the same four rule
// sets — because the acceptance criteria this package's tooling has to meet
// is "the same bar", not "a bar of its own"; keep the two in sync by hand if
// either ever changes, there being no way for a `rector.php` to `include`
// another the way a `phpstan.neon` can.
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withCache(cacheDirectory: __DIR__ . '/build/cache/rector')
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
    ]);
