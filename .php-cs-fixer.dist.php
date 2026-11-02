<?php

declare(strict_types=1);

// Scoped to this package alone: no monorepo `src/` or `tests/` —
// everything this `Finder` walks lives under this tree. There is no
// `exclude(['fixtures/generated'])` or generated-`*Test.php` `notPath()`:
// this package emits nothing, so it carries no generated-code tier to
// exempt from formatting.
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

// The rule set mirrors the monorepo root `.php-cs-fixer.dist.php`'s exactly, for the same
// reason `rector.php`'s comment gives: the acceptance criteria this
// package's tooling has to meet is the same bar the root holds the
// generator to, not a bar of its own. A `PhpCsFixer\Config` has no
// `include` mechanism the way a PHPStan config does, so there is no way to
// have one delegate to the other — keep the two `setRules()` calls in sync
// by hand if either ever changes.
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2x0' => true,
        '@PER-CS2x0:risky' => true,
        '@PHP8x4Migration' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'fully_qualified_strict_types' => ['import_symbols' => true],
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'no_unused_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/build/cache/.php-cs-fixer.cache');
