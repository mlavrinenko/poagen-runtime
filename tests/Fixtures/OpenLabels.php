<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\AdditionalPropertyBag;
use Poagen\Runtime\Attribute\AdditionalProperties;

/**
 * The shape `AdditionalPropertyEmitter` emits for `additionalProperties: true`
 * beside declared `properties`: one named property, and a bag for whatever
 * else the payload carried.
 *
 * {@see Labels} is the same shape with the extra values typed by a schema, so
 * the two differ only where the document did — one marker, two carriers.
 */
final readonly class OpenLabels
{
    public function __construct(
        public ?string $primary = null,
        #[AdditionalProperties]
        public AdditionalPropertyBag $additionalProperties = new AdditionalPropertyBag(),
    ) {}
}
