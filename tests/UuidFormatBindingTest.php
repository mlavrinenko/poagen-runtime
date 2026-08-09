<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Format\RamseyUuid;
use Poagen\Runtime\Format\SymfonyUuid;
use Ramsey\Uuid\Uuid as RamseyLibrary;
use Symfony\Component\Uid\Uuid as SymfonyLibrary;

/**
 * `built-in-uuid-and-binary-format-bindings`: the two bindings this package
 * ships for `format: uuid`, neither of them applied by default and neither
 * of their libraries a `require`.
 */
final class UuidFormatBindingTest extends TestCase
{
    private const string CANONICAL = '2b1a7f60-4c2c-4a5b-9c6e-6a2f0d1e3b44';

    public function testRamseyRoundTripsTheCanonicalForm(): void
    {
        $binding = RamseyUuid::fromScalar(self::CANONICAL);

        self::assertTrue($binding->value->equals(RamseyLibrary::fromString(self::CANONICAL)));
        self::assertSame(self::CANONICAL, $binding->toScalar());
        self::assertSame(self::CANONICAL, (string) $binding);
    }

    public function testSymfonyRoundTripsTheCanonicalForm(): void
    {
        $binding = SymfonyUuid::fromScalar(self::CANONICAL);

        self::assertTrue($binding->value->equals(SymfonyLibrary::fromString(self::CANONICAL)));
        self::assertSame(self::CANONICAL, $binding->toScalar());
        self::assertSame(self::CANONICAL, (string) $binding);
    }

    /**
     * Upper case in, canonical out. A binding that echoed back whatever the
     * payload happened to spell would put two spellings of one identifier on
     * the wire depending on which end wrote it.
     */
    public function testBothNormaliseCaseOnTheWayOut(): void
    {
        $shouted = strtoupper(self::CANONICAL);

        self::assertSame(self::CANONICAL, RamseyUuid::fromScalar($shouted)->toScalar());
        self::assertSame(self::CANONICAL, SymfonyUuid::fromScalar($shouted)->toScalar());
    }

    /**
     * `symfony/uid` accepts base58 and base32 spellings on the way in, and
     * its own string cast is configurable. `toScalar()` pins RFC 4122 so the
     * wire form is a property of the schema rather than of the consumer's
     * container.
     */
    public function testSymfonyEmitsRfc4122EvenWhenTheInputWasNot(): void
    {
        $base58 = SymfonyLibrary::fromString(self::CANONICAL)->toBase58();

        self::assertNotSame(self::CANONICAL, $base58);
        self::assertSame(self::CANONICAL, SymfonyUuid::fromScalar($base58)->toScalar());
    }

    /**
     * @return list<array{class-string<RamseyUuid|SymfonyUuid>}>
     */
    public static function bindings(): array
    {
        return [[RamseyUuid::class], [SymfonyUuid::class]];
    }

    /**
     * A non-string payload is rejected here rather than at the library, whose
     * message would name the library instead of the wire value that was
     * actually wrong.
     *
     * @param class-string<RamseyUuid|SymfonyUuid> $binding
     */
    #[DataProvider('bindings')]
    public function testANonStringPayloadIsRefusedNamingTheFormat(string $binding): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('format: uuid expects a string payload, got int');

        $binding::fromScalar(42);
    }

    /**
     * @param class-string<RamseyUuid|SymfonyUuid> $binding
     */
    #[DataProvider('bindings')]
    public function testAMalformedStringIsRefused(string $binding): void
    {
        $this->expectException(InvalidArgumentException::class);

        $binding::fromScalar('not-a-uuid');
    }
}
