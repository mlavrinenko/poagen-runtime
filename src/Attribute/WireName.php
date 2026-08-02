<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Attribute;

use Attribute;

/**
 * Property-level: the wire (JSON) key that backs this PHP property when its
 * name diverges from the schema key. Emitted only when PHP name ≠ wire key.
 *
 * See `attribute-and-phpdoc-driven-serialization`.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class WireName
{
    public function __construct(public string $name) {}
}
