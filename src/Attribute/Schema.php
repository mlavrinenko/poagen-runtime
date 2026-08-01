<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Attribute;

use Attribute;

/**
 * Class-level: the OpenAPI component name that produced this Model. Reserved
 * for tooling that needs to round-trip the original schema name (e.g.
 * discriminator wiring, doc generation).
 *
 * See ADR-010.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Schema
{
    public function __construct(public ?string $name = null) {}
}
