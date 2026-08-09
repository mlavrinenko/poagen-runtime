<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Marshal\Hydrator;
use Poagen\Runtime\Marshal\Marshaller;
use Poagen\Runtime\Tests\Fixtures\Journey;
use Poagen\Runtime\Tests\Fixtures\Point;
use Poagen\Runtime\Tests\Fixtures\Tag;
use Poagen\Runtime\Tests\Fixtures\Warehouse;

/**
 * Direct unit coverage for {@see Marshaller} and {@see Hydrator},
 * independent of the generator pipeline.
 * `attribute-and-phpdoc-driven-serialization` in scope.
 */
final class MarshalRoundTripTest extends TestCase
{
    public function testWireNameRoundTrip(): void
    {
        $contact = new Fixtures\Contact(displayName: 'Ada', email: new Fixtures\Email('ada@example'));

        $array = new Marshaller()->marshal($contact);

        self::assertSame(
            ['display-name' => 'Ada', 'email' => 'ada@example'],
            $array,
        );

        $hydrated = new Hydrator()->hydrate(Fixtures\Contact::class, ['display-name' => 'Grace', 'email' => 'grace@example']);

        self::assertSame('Grace', $hydrated->displayName);
        self::assertInstanceOf(Fixtures\Email::class, $hydrated->email);
        self::assertSame('grace@example', $hydrated->email->value);
    }

    public function testDateTimeAndEnumRoundTrip(): void
    {
        $order = new Fixtures\Order(
            id: 7,
            petId: 42,
            shipDate: new DateTimeImmutable('2026-04-18T12:34:56+00:00'),
            status: Fixtures\Status::Placed,
        );

        $array = new Marshaller()->marshal($order);

        self::assertSame(7, $array['id']);
        self::assertSame(42, $array['pet_id']);
        self::assertSame('2026-04-18T12:34:56+00:00', $array['shipDate']);
        self::assertSame('placed', $array['status']);

        $hydrated = new Hydrator()->hydrate(Fixtures\Order::class, [
            'id' => 9,
            'pet_id' => 101,
            'shipDate' => '2026-05-01T10:20:30+02:00',
            'status' => 'approved',
        ]);

        self::assertSame(9, $hydrated->id);
        self::assertSame(101, $hydrated->petId);
        self::assertInstanceOf(DateTimeImmutable::class, $hydrated->shipDate);
        self::assertSame('2026-05-01T10:20:30+02:00', $hydrated->shipDate->format(DateTimeInterface::RFC3339));
        self::assertSame(Fixtures\Status::Approved, $hydrated->status);

        $delivered = new Hydrator()->hydrate(Fixtures\Order::class, ['status' => 'delivered']);
        self::assertSame(Fixtures\Status::Delivered, $delivered->status);
    }

    public function testListOfModelsRecurses(): void
    {
        $pet = new Fixtures\Pet(id: 1, name: 'Rex', tags: [new Tag(1, 'friendly'), new Tag(2, 'playful')]);
        $array = new Marshaller()->marshal($pet);

        self::assertSame(
            [['id' => 1, 'name' => 'friendly'], ['id' => 2, 'name' => 'playful']],
            $array['tags'],
        );

        $hydrated = new Hydrator()->hydrate(Fixtures\Pet::class, [
            'id' => 1,
            'name' => 'Rex',
            'photoUrls' => ['https://example/a.jpg', 'https://example/b.jpg'],
            'tags' => [['id' => 1, 'name' => 'friendly'], ['id' => 2, 'name' => 'playful']],
        ]);

        self::assertSame(1, $hydrated->id);
        self::assertSame('Rex', $hydrated->name);
        self::assertSame(['https://example/a.jpg', 'https://example/b.jpg'], $hydrated->photoUrls);
        self::assertNotNull($hydrated->tags);
        self::assertCount(2, $hydrated->tags);
        self::assertInstanceOf(Tag::class, $hydrated->tags[0]);
        self::assertSame(1, $hydrated->tags[0]->id);
        self::assertSame('playful', $hydrated->tags[1]->name);
    }

    public function testMapOfModelsRoundTrips(): void
    {
        // `Warehouse::$bins` is `array<string, Tag>|null` in phpdoc — the
        // two-argument generic array shape `PropertyTypeResolver::
        // resolveArrayLike()` resolves to `PropertyType::KIND_MAP`. Nothing
        // in the fixture tree exercised that arm before this test: both
        // `Marshaller::encode()` and `Hydrator::decode()`'s `KIND_MAP` match
        // arms ran zero times across the whole suite.
        $warehouse = new Warehouse(
            name: 'north',
            bins: ['a' => new Tag(1, 'friendly'), 'b' => new Tag(2, 'playful')],
        );

        $array = new Marshaller()->marshal($warehouse);

        self::assertSame(
            [
                'name' => 'north',
                'bins' => [
                    'a' => ['id' => 1, 'name' => 'friendly'],
                    'b' => ['id' => 2, 'name' => 'playful'],
                ],
            ],
            $array,
        );

        $hydrated = new Hydrator()->hydrate(Warehouse::class, [
            'name' => 'south',
            'bins' => [
                'x' => ['id' => 3, 'name' => 'quiet'],
                'y' => ['id' => 4, 'name' => 'loud'],
            ],
        ]);

        self::assertSame('south', $hydrated->name);
        self::assertNotNull($hydrated->bins);
        self::assertCount(2, $hydrated->bins);

        // The recovered values must be typed `Tag` instances (proving
        // `decodeMap()` ran its item-type recursion), not a passthrough of
        // the raw wire arrays.
        self::assertArrayHasKey('x', $hydrated->bins);
        self::assertInstanceOf(Tag::class, $hydrated->bins['x']);
        self::assertSame(3, $hydrated->bins['x']->id);
        self::assertSame('quiet', $hydrated->bins['x']->name);

        self::assertArrayHasKey('y', $hydrated->bins);
        self::assertInstanceOf(Tag::class, $hydrated->bins['y']);
        self::assertSame(4, $hydrated->bins['y']->id);
        self::assertSame('loud', $hydrated->bins['y']->name);
    }

    public function testDiscriminatedUnionPicksTheCorrectBranch(): void
    {
        $hydrated = new Hydrator()->hydrate(Fixtures\Animal::class, [
            'creature' => ['petType' => 'Cat', 'bark' => 'arf', 'meow' => 'purr'],
        ]);

        self::assertInstanceOf(Fixtures\Cat::class, $hydrated->creature);
        // `petType` is the discriminator's wire field, and it maps onto the
        // branch's own property like any other — not consumed only for
        // branch selection.
        self::assertSame('Cat', $hydrated->creature->petType);
        self::assertSame('purr', $hydrated->creature->meow);

        $hydratedDog = new Hydrator()->hydrate(Fixtures\Animal::class, [
            'creature' => ['petType' => 'Dog', 'bark' => 'woof'],
        ]);
        self::assertInstanceOf(Fixtures\Dog::class, $hydratedDog->creature);
        self::assertSame('Dog', $hydratedDog->creature->petType);
        self::assertSame('woof', $hydratedDog->creature->bark);
    }

    public function testMissingWireKeyOmitsArgument(): void
    {
        $hydrated = new Hydrator()->hydrate(Fixtures\Order::class, ['id' => 5]);

        self::assertSame(5, $hydrated->id);
        self::assertNull($hydrated->petId);
        self::assertNull($hydrated->shipDate);
    }

    public function testScalarBindingValueObjectRoundTrip(): void
    {
        $contact = new Fixtures\Contact(displayName: 'Ada', email: Fixtures\Email::fromScalar('ada@example'));
        $encoded = (string) json_encode(new Marshaller()->marshal($contact));

        self::assertStringContainsString('"email":"ada@example"', $encoded);
        self::assertStringNotContainsString('"value"', $encoded);
    }

    /**
     * `codec-bindings-for-foreign-value-types`: a codec-bound property is
     * declared as its target type directly — {@see Point}, never a wrapper
     * — and marshalling calls the registered {@see Fixtures\PointCodec}
     * rather than a method on the value itself, since `Point` implements
     * nothing of poagen's.
     */
    public function testCodecBoundScalarRoundTrips(): void
    {
        $journey = new Journey(origin: new Point(1, 2));

        self::assertSame(
            ['origin' => '1,2', 'destination' => null, 'waypoints' => null, 'landmarks' => null],
            new Marshaller()->marshal($journey),
        );

        $hydrated = new Hydrator()->hydrate(Journey::class, ['origin' => '3,4']);

        self::assertInstanceOf(Point::class, $hydrated->origin);
        self::assertSame(3, $hydrated->origin->x);
        self::assertSame(4, $hydrated->origin->y);
    }

    public function testCodecBoundNullablePropertyRoundTrips(): void
    {
        $withDestination = new Journey(origin: new Point(0, 0), destination: new Point(5, 6));
        self::assertSame('5,6', new Marshaller()->marshal($withDestination)['destination']);

        $withoutDestination = new Journey(origin: new Point(0, 0));
        self::assertNull(new Marshaller()->marshal($withoutDestination)['destination']);

        $hydrated = new Hydrator()->hydrate(Journey::class, ['origin' => '0,0']);
        self::assertNull($hydrated->destination);
    }

    public function testCodecBoundListPropertyRoundTrips(): void
    {
        $journey = new Journey(origin: new Point(0, 0), waypoints: [new Point(1, 1), new Point(2, 2)]);

        self::assertSame(['1,1', '2,2'], new Marshaller()->marshal($journey)['waypoints']);

        $hydrated = new Hydrator()->hydrate(Journey::class, ['origin' => '0,0', 'waypoints' => ['1,1', '2,2']]);

        self::assertNotNull($hydrated->waypoints);
        self::assertCount(2, $hydrated->waypoints);
        self::assertInstanceOf(Point::class, $hydrated->waypoints[0]);
        self::assertSame(1, $hydrated->waypoints[0]->x);
        self::assertSame(2, $hydrated->waypoints[1]->y);
    }

    public function testCodecBoundMapPropertyRoundTrips(): void
    {
        $journey = new Journey(origin: new Point(0, 0), landmarks: ['start' => new Point(0, 0), 'end' => new Point(9, 9)]);

        self::assertSame(
            ['start' => '0,0', 'end' => '9,9'],
            new Marshaller()->marshal($journey)['landmarks'],
        );

        $hydrated = new Hydrator()->hydrate(Journey::class, [
            'origin' => '0,0',
            'landmarks' => ['start' => '0,0', 'end' => '9,9'],
        ]);

        self::assertNotNull($hydrated->landmarks);
        self::assertInstanceOf(Point::class, $hydrated->landmarks['end']);
        self::assertSame(9, $hydrated->landmarks['end']->x);
    }

    /**
     * A codec that refuses a malformed payload throws naming the format —
     * {@see Fixtures\PointCodec} does, the same as `Format\Base64Codec` and
     * `Format\RamseyUuidCodec` — and the Hydrator prepends which property it
     * was decoding, so the message names both what was wrong and where.
     */
    public function testCodecDecodeFailurePrependsThePropertyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("property 'origin': format: point expects 'x,y'");

        new Hydrator()->hydrate(Journey::class, ['origin' => 'not-a-point']);
    }
}
