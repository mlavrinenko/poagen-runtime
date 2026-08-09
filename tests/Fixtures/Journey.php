<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\Attribute\Format;
use Poagen\Runtime\Codec;
use Poagen\Runtime\Marshal\PropertyTypeResolver;

/**
 * Every shape a codec-bound property can take: required, optional
 * (nullable), a list, and a map — `codec-bindings-for-foreign-value-types`'s
 * own acceptance criterion. `#[Format]` carries `codec:` on all four, since
 * {@see PropertyTypeResolver} has no other way to
 * learn which {@see Codec} converts a list or map item —
 * reflection sees only `array` at that level, never the item's own class.
 */
final readonly class Journey
{
    /**
     * @param list<Point>|null         $waypoints
     * @param array<string, Point>|null $landmarks
     */
    public function __construct(
        #[Format('point', codec: PointCodec::class)]
        public Point $origin,
        #[Format('point', codec: PointCodec::class)]
        public ?Point $destination = null,
        #[Format('point', codec: PointCodec::class)]
        public ?array $waypoints = null,
        #[Format('point', codec: PointCodec::class)]
        public ?array $landmarks = null,
    ) {}
}
