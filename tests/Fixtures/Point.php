<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

/**
 * A type poagen does not control, standing in for a real third-party value
 * object (`Ramsey\Uuid\UuidInterface`, `Brick\Money\Money`) in the codec
 * round-trip tests — see {@see PointCodec}.
 */
final readonly class Point
{
    public function __construct(
        public int $x,
        public int $y,
    ) {}
}
