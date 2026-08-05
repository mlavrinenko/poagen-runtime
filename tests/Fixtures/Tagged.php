<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Attribute;

/**
 * Method-level attribute used only to give DocScanTargets::second() a real
 * `#[...]` group between its docblock and its `function` keyword, with a
 * nested-bracket argument so `MethodDocScanner::skipAttributeGroup()`'s
 * depth counter has more than one `[`/`]` pair to walk past.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Tagged
{
    /**
     * `$values` is intentionally unread — this class exists only to be
     * referenced syntactically, never reflected or instantiated.
     *
     * @param list<string> $values
     */
    public function __construct(array $values) {}
}
