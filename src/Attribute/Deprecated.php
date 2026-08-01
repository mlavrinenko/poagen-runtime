<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Attribute;

use Attribute;

/**
 * Property-level: passthrough of an OpenAPI `deprecated: true` flag. Carries
 * the optional replacement hint used by docs/IDE tooling.
 *
 * See ADR-010.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Deprecated
{
    public function __construct(public ?string $replacement = null) {}
}
