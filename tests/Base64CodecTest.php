<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Format\Base64Codec;

/**
 * `base64-binding-for-format-byte`: unlike `format: uuid`, `byte` costs no
 * consumer a dependency — both directions are PHP core — so this codec is a
 * poagen built-in rather than a candidate a profile has to select.
 */
final class Base64CodecTest extends TestCase
{
    public function testTargetsAPlainString(): void
    {
        self::assertSame('string', Base64Codec::targetType());
    }

    public function testDecodesValidBase64(): void
    {
        self::assertSame('hello', Base64Codec::decode('aGVsbG8='));
    }

    public function testDecodesEmptyStringToEmptyBytes(): void
    {
        self::assertSame('', Base64Codec::decode(''));
    }

    public function testEncodesBackToBase64(): void
    {
        self::assertSame('aGVsbG8=', Base64Codec::encode('hello'));
    }

    public function testRoundTripsArbitraryBytes(): void
    {
        $bytes = "\x00\x01\xFF\xFE binary \x7F";

        self::assertSame($bytes, Base64Codec::decode(Base64Codec::encode($bytes)));
    }

    /**
     * Strict mode: a payload with a character outside the base64 alphabet is
     * refused rather than decoded into corrupted bytes, which is what PHP's
     * lenient `base64_decode()` mode does by default.
     */
    public function testRefusesAPayloadWithAnInvalidCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('format: byte expects a base64-encoded string');

        Base64Codec::decode('not base64!!');
    }

    public function testRefusesAPayloadWithBadlyPlacedPadding(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Base64Codec::decode('a=b=');
    }

    public function testRefusesANonStringPayloadNamingTheFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('format: byte expects a string payload, got int');

        Base64Codec::decode(42);
    }
}
