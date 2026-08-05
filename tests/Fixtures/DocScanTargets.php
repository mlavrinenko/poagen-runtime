<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use LogicException;

/**
 * Scanned directly by `MethodDocScanner::docFor()` in
 * DescriptorParserFallbackTest — never instantiated, never called (each
 * body throws rather than sitting empty, so Rector does not remove it as
 * dead code). Three shapes `MethodDocScanner::scan()` has to tell apart in
 * one pass: a plain docblock, a docblock separated from `function` by a
 * `#[...]` attribute group, and a method with no docblock at all. `second`
 * deliberately sits in the middle (not last), so a mutant that makes
 * `skipAttributeGroup()` over- or under-run its bracket depth (or the
 * whole file) is caught by `second`'s own doc going missing, not just
 * `third`'s already-missing one.
 */
final class DocScanTargets
{
    /**
     * First method's own doc comment.
     */
    public function first(): never
    {
        throw new LogicException('reflected only, never called');
    }

    /**
     * Second method's own, distinct doc comment.
     */
    #[Tagged(['a', 'b'])]
    public function second(): never
    {
        throw new LogicException('reflected only, never called');
    }

    public function third(): never
    {
        throw new LogicException('reflected only, never called');
    }
}
