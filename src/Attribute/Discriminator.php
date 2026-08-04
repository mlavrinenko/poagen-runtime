<?php

declare(strict_types=1);

namespace Poagen\Runtime\Attribute;

use Attribute;
use Poagen\Runtime\Marshal\UnionBranchException;

/**
 * Property-level: the wire field whose value selects a branch of a PHP union
 * type. `$map` carries an OpenAPI `discriminator.mapping` (or the generator-
 * derived equivalent), keyed by wire tag and valued by branch FQCN.
 *
 * `$onParent` says where to read that field. The default, `false`, reads it
 * from the union value itself, which is where OpenAPI's own `discriminator`
 * puts it. `true` reads it from the payload of the object that *declares* this
 * property — a sibling of the union rather than a field inside it. That is
 * where a real vendor often puts the tag: GitHub's `event.payload` picks among
 * sixteen branches on the sibling `event.type`, and no field inside the value
 * could do the job at all, because one branch (`public-event`) declares no
 * properties. See `declare-an-undiscriminated-union-as-raw-data`.
 *
 * A tag that is absent, or that carries a value `$map` does not name, raises
 * {@see UnionBranchException} rather than
 * falling back to a branch. `guidance-cannot-mislead`: a mapping the consumer
 * wrote is a claim, and a value outside it is a gap they should hear about.
 *
 * See `attribute-and-phpdoc-driven-serialization`.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Discriminator
{
    /**
     * @param array<string, class-string> $map
     */
    public function __construct(
        public string $field,
        public array $map,
        public bool $onParent = false,
    ) {}
}
