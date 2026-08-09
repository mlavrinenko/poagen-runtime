<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Format\RamseyUuidCodec;
use Poagen\Runtime\Format\SymfonyUuidCodec;
use Ramsey\Uuid\Uuid as RamseyLibrary;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\Uuid as SymfonyLibrary;

/**
 * `built-in-uuid-and-binary-format-bindings` shipped these as `ScalarBinding`
 * wrappers; `codec-bindings-for-foreign-value-types` re-expressed them as
 * codecs so the emitted property is the library's own `UuidInterface`/`Uuid`
 * type rather than a wrapper around it. Neither codec applies by default,
 * and neither library is a `require` of this package.
 */
final class UuidFormatBindingTest extends TestCase
{
    private const string CANONICAL = '2b1a7f60-4c2c-4a5b-9c6e-6a2f0d1e3b44';

    public function testRamseyTargetsTheLibraryInterface(): void
    {
        self::assertSame(UuidInterface::class, RamseyUuidCodec::targetType());
    }

    public function testSymfonyTargetsTheLibraryClass(): void
    {
        self::assertSame(SymfonyLibrary::class, SymfonyUuidCodec::targetType());
    }

    public function testRamseyRoundTripsTheCanonicalForm(): void
    {
        $decoded = RamseyUuidCodec::decode(self::CANONICAL);

        self::assertTrue($decoded->equals(RamseyLibrary::fromString(self::CANONICAL)));
        self::assertSame(self::CANONICAL, RamseyUuidCodec::encode($decoded));
    }

    public function testSymfonyRoundTripsTheCanonicalForm(): void
    {
        $decoded = SymfonyUuidCodec::decode(self::CANONICAL);

        self::assertTrue($decoded->equals(SymfonyLibrary::fromString(self::CANONICAL)));
        self::assertSame(self::CANONICAL, SymfonyUuidCodec::encode($decoded));
    }

    /**
     * Upper case in, canonical out. A codec that echoed back whatever the
     * payload happened to spell would put two spellings of one identifier on
     * the wire depending on which end wrote it.
     */
    public function testBothNormaliseCaseOnTheWayOut(): void
    {
        $shouted = strtoupper(self::CANONICAL);

        self::assertSame(self::CANONICAL, RamseyUuidCodec::encode(RamseyUuidCodec::decode($shouted)));
        self::assertSame(self::CANONICAL, SymfonyUuidCodec::encode(SymfonyUuidCodec::decode($shouted)));
    }

    /**
     * `symfony/uid` accepts base58 and base32 spellings on the way in, and
     * its own string cast is configurable. `encode()` pins RFC 4122 so the
     * wire form is a property of the schema rather than of the consumer's
     * container.
     */
    public function testSymfonyEmitsRfc4122EvenWhenTheInputWasNot(): void
    {
        $base58 = SymfonyLibrary::fromString(self::CANONICAL)->toBase58();

        self::assertNotSame(self::CANONICAL, $base58);
        self::assertSame(self::CANONICAL, SymfonyUuidCodec::encode(SymfonyUuidCodec::decode($base58)));
    }

    /**
     * @return list<array{class-string<RamseyUuidCodec|SymfonyUuidCodec>}>
     */
    public static function codecs(): array
    {
        return [[RamseyUuidCodec::class], [SymfonyUuidCodec::class]];
    }

    /**
     * A non-string payload is rejected here rather than at the library, whose
     * message would name the library instead of the wire value that was
     * actually wrong.
     *
     * @param class-string<RamseyUuidCodec|SymfonyUuidCodec> $codec
     */
    #[DataProvider('codecs')]
    public function testANonStringPayloadIsRefusedNamingTheFormat(string $codec): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('format: uuid expects a string payload, got int');

        $codec::decode(42);
    }

    /**
     * @param class-string<RamseyUuidCodec|SymfonyUuidCodec> $codec
     */
    #[DataProvider('codecs')]
    public function testAMalformedStringIsRefused(string $codec): void
    {
        $this->expectException(InvalidArgumentException::class);

        $codec::decode('not-a-uuid');
    }
}
