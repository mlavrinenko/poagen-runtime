<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\BindingContext;
use Poagen\Runtime\Marshal\Hydrator;
use Poagen\Runtime\Marshal\Marshaller;
use Poagen\Runtime\Tests\Fixtures\Email;
use Poagen\Runtime\Tests\Fixtures\Order;
use Poagen\Runtime\Tests\Fixtures\Shift;
use Poagen\Runtime\Tests\Fixtures\WallClock;

use function date_default_timezone_get;

/**
 * A `date-time` the document left without an offset is off-spec by RFC 3339,
 * and PHP resolves such a string against the process timezone. These pin the
 * replacement: the consumer states the zone once, and it reaches both the
 * built-in `\DateTimeImmutable` path and any value object that asked for it —
 * without disturbing a value that carried its own offset, a binding that did
 * not opt in, or a consumer who states nothing at all.
 */
final class BindingContextTest extends TestCase
{
    public function testAnOffsetFreeDateTimeTakesTheStatedZone(): void
    {
        $order = $this->hydrator('Europe/Moscow')->hydrate(Order::class, ['shipDate' => '2026-08-14 12:51:17']);

        self::assertSame('2026-08-14T12:51:17+03:00', $order->shipDate?->format('c'));
    }

    /**
     * The narrowness that makes a single stated zone safe on a document that
     * mixes compliant and off-spec values: PHP ignores the zone argument when
     * the string carries its own offset, so this can never overwrite one the
     * vendor did state.
     */
    public function testAValueCarryingItsOwnOffsetIgnoresTheStatedZone(): void
    {
        $order = $this->hydrator('Europe/Moscow')->hydrate(Order::class, ['shipDate' => '2026-08-14T12:51:17+05:00']);

        self::assertSame('2026-08-14T12:51:17+05:00', $order->shipDate?->format('c'));
    }

    public function testStatingNothingKeepsThePreviousBehaviour(): void
    {
        $order = new Hydrator()->hydrate(Order::class, ['shipDate' => '2026-08-14 12:51:17']);

        self::assertSame(date_default_timezone_get(), $order->shipDate?->getTimezone()->getName());
    }

    public function testAContextualBindingIsGivenTheStatedZone(): void
    {
        $shift = $this->hydrator('Europe/Moscow')->hydrate(Shift::class, ['startsAt' => '2026-08-14 12:51:17']);

        self::assertSame('2026-08-14T12:51:17+03:00', $shift->startsAt?->value->format('c'));
    }

    /**
     * A binding that did not opt in keeps going through the static
     * `fromScalar()` it has always had, on the same model, in the same call.
     */
    public function testAPlainScalarBindingIsUntouched(): void
    {
        $shift = $this->hydrator('Europe/Moscow')->hydrate(Shift::class, ['owner' => 'grace@example']);

        self::assertSame('grace@example', $shift->owner?->value);
    }

    /**
     * The way back out asks the binding with the same statement, so a value
     * built in any zone leaves as the vendor's wall clock.
     */
    public function testSerialisationAsksTheBindingWithTheSameContext(): void
    {
        $shift = new Shift(new WallClock(new DateTimeImmutable('2026-08-14T09:51:17+00:00')));

        $wire = $this->marshaller('Europe/Moscow')->marshal($shift);

        self::assertSame('2026-08-14 12:51:17', $wire['startsAt']);
    }

    public function testARoundTripThroughTheWireIsStable(): void
    {
        $shift = $this->hydrator('Europe/Moscow')->hydrate(Shift::class, ['startsAt' => '2026-08-14 12:51:17']);

        $wire = $this->marshaller('Europe/Moscow')->marshal($shift);

        self::assertSame('2026-08-14 12:51:17', $wire['startsAt']);
    }

    /**
     * Two clients, one process, different installations. This is what a
     * constant in the value object cannot do, and the reason the statement
     * lives on the context rather than in the binding.
     */
    public function testTwoContextsReadTheSamePayloadAsDifferentInstants(): void
    {
        $payload = ['startsAt' => '2026-08-14 12:51:17'];

        $moscow = $this->hydrator('Europe/Moscow')->hydrate(Shift::class, $payload);
        $tokyo = $this->hydrator('Asia/Tokyo')->hydrate(Shift::class, $payload);

        self::assertNotSame(
            $moscow->startsAt?->value->getTimestamp(),
            $tokyo->startsAt?->value->getTimestamp(),
        );
    }

    public function testAnEmailStillHydratesWithNoContextAtAll(): void
    {
        $shift = new Hydrator()->hydrate(Shift::class, ['owner' => 'grace@example']);

        self::assertEquals(new Email('grace@example'), $shift->owner);
    }

    private function hydrator(string $zone): Hydrator
    {
        return new Hydrator(context: new BindingContext(new DateTimeZone($zone)));
    }

    private function marshaller(string $zone): Marshaller
    {
        return new Marshaller(context: new BindingContext(new DateTimeZone($zone)));
    }
}
