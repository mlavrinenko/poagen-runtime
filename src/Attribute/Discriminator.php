<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Attribute;

use Attribute;

/**
 * Property-level: the wire field whose value selects a branch of a PHP union
 * type. `$map` carries an OpenAPI `discriminator.mapping` (or the generator-
 * derived equivalent), keyed by wire tag and valued by branch FQCN.
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
    ) {}
}
