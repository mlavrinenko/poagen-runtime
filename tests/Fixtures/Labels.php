<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\Attribute\AdditionalProperties;

/**
 * The shape `AdditionalPropertyEmitter` emits for a schema declaring
 * `properties` and a scalar `additionalProperties`: one named property, and
 * one map for whatever else the payload carried.
 */
final readonly class Labels
{
    /**
     * @param array<string, string> $additionalProperties
     */
    public function __construct(
        public ?string $primary = null,
        #[AdditionalProperties]
        public array $additionalProperties = [],
    ) {}
}
