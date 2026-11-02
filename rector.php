<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

// Scoped to this package alone: every path is relative to this file, so the
// tree stays checkable wherever it is checked out. The set list mirrors the
// monorepo root `rector.php`'s exactly — same PHP-8.4 level set and the same
// four rule sets — because this package's tooling meets the same bar, not a
// bar of its own; keep the two in sync by hand if either ever changes, there
// being no way for a `rector.php` to `include` another the way a
// `phpstan.neon` can.
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
