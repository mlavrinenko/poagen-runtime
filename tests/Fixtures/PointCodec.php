<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use InvalidArgumentException;
use Poagen\Runtime\Codec;
use Poagen\Runtime\Http\ResponseDecoder;
use Poagen\Runtime\Marshal\Hydrator;
use Poagen\Runtime\Marshal\Marshaller;

use function assert;
use function count;
use function explode;
use function is_string;
use function sprintf;

/**
 * A codec for {@see Point}, a stand-in for a foreign value object — proves
 * {@see Hydrator}, {@see Marshaller}
 * and {@see ResponseDecoder} round-trip a codec-bound
 * scalar, nullable, list and map property without the target type ever
 * implementing anything of poagen's.
 */
final readonly class PointCodec implements Codec
{
    public static function targetType(): string
    {
        return Point::class;
    }

    public static function decode(mixed $wire): Point
    {
        if (!is_string($wire)) {
            throw new InvalidArgumentException('format: point expects a string payload');
        }

        $parts = explode(',', $wire);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException(sprintf("format: point expects 'x,y', got '%s'", $wire));
        }

        return new Point((int) $parts[0], (int) $parts[1]);
    }

    public static function encode(mixed $value): string
    {
        assert($value instanceof Point);

        return $value->x . ',' . $value->y;
    }
}
