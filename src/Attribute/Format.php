<?php

declare(strict_types=1);

namespace Poagen\Runtime\Attribute;

use Attribute;
use Poagen\Runtime\Codec;

/**
 * Property-level: the OpenAPI `format` hint. Drives scalar conversion in the
 * runtime — a built-in (`'date-time'` → `\DateTimeImmutable`) needs only the
 * format string, but a codec-bound property also needs `$codec`, because the
 * property is declared as the codec's foreign target type and reflection
 * alone cannot say which registered {@see Codec} converts it
 * (`codec-bindings-for-foreign-value-types`).
 *
 * See `attribute-and-phpdoc-driven-serialization`.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Format
{
    /**
     * @param class-string<Codec>|null $codec
     */
    public function __construct(
        public string $format,
        public ?string $codec = null,
    ) {}
}
