<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Attribute;

use Attribute;

/**
 * Property-level: the OpenAPI `format` hint. Drives scalar codec selection in
 * the runtime (e.g. `'date-time'` → `\DateTimeImmutable` round-trip).
 *
 * See ADR-010.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Format
{
    public function __construct(public string $format) {}
}
