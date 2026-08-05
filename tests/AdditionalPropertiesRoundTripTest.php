<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Poagen\Runtime\AdditionalPropertyBag;
use Poagen\Runtime\Marshal\Hydrator;
use Poagen\Runtime\Marshal\Marshaller;

/**
 * declared-shapes-become-types: a schema declaring both `properties` and
 * anything beyond them emits a class carrying both, and both survive the wire.
 *
 * A declared property is found by its wire name; extra keys have none, so the
 * property carrying them is found by `#[AdditionalProperties]` instead. That
 * marker is the whole mechanism — without it, the generator would emit a typed
 * map nothing ever fills, which is a new dishonesty in place of the old one.
 *
 * One marker, two carriers, and the document decides which: a schema for the
 * extra values gives a typed map, a bare `additionalProperties: true` gives an
 * {@see AdditionalPropertyBag}, because a document that declared no value type
 * leaves nothing to type a map with.
 */
final class AdditionalPropertiesRoundTripTest extends TestCase
{
    public function testExtraKeysLandInTheMapAndComeBackOutBesideTheDeclaredOnes(): void
    {
        $payload = ['primary' => 'front', 'left' => 'port', 'right' => 'starboard'];

        $labels = new Hydrator()->hydrate(Fixtures\Labels::class, $payload);

        self::assertSame('front', $labels->primary);
        self::assertSame(['left' => 'port', 'right' => 'starboard'], $labels->additionalProperties);

        // Beside, not nested: the extras were keys of the payload and that is
        // where they go back, in a stable order.
        self::assertSame($payload, new Marshaller()->marshal($labels));
    }

    public function testADeclaredPropertyKeepsItsOwnKey(): void
    {
        $labels = new Hydrator()->hydrate(Fixtures\Labels::class, ['primary' => 'front']);

        self::assertSame('front', $labels->primary);
        self::assertSame([], $labels->additionalProperties);
    }

    public function testAnAbsentDeclaredPropertyDoesNotPutItsKeyInTheMap(): void
    {
        $labels = new Hydrator()->hydrate(Fixtures\Labels::class, ['left' => 'port']);

        self::assertNull($labels->primary);
        self::assertSame(['left' => 'port'], $labels->additionalProperties);
    }

    public function testExtraValuesAreTypedByTheSchemaThatDeclaredThem(): void
    {
        $inventory = new Hydrator()->hydrate(Fixtures\Inventory::class, [
            'warehouse' => 'north',
            'spare' => ['id' => 7, 'name' => 'bolt'],
        ]);

        $spare = $inventory->additionalProperties['spare'] ?? null;

        self::assertSame('north', $inventory->warehouse);
        self::assertInstanceOf(Fixtures\Tag::class, $spare);
        self::assertSame(7, $spare->id);
        self::assertSame('bolt', $spare->name);
    }

    public function testTypedExtraValuesMarshalBackToTheirWireForm(): void
    {
        $inventory = new Fixtures\Inventory('north', ['spare' => new Fixtures\Tag(7, 'bolt')]);

        self::assertSame(
            ['warehouse' => 'north', 'spare' => ['id' => 7, 'name' => 'bolt']],
            new Marshaller()->marshal($inventory),
        );
    }

    public function testAnExtraKeyCannotOverwriteADeclaredWireName(): void
    {
        // Only reachable by constructing the model by hand — the hydrator never
        // puts a declared key in the map. The declared property still wins,
        // because a model that could be marshalled into a payload it cannot be
        // hydrated back from would not round-trip at all.
        $labels = new Fixtures\Labels('front', ['primary' => 'smuggled', 'left' => 'port']);

        self::assertSame(['primary' => 'front', 'left' => 'port'], new Marshaller()->marshal($labels));
    }

    public function testUntypedExtraKeysLandInTheBagAndComeBackOutBesideTheDeclaredOnes(): void
    {
        // `additionalProperties: true`. Same marker, same collection pass, a
        // different carrier — because there is no declared value type here to
        // build a typed map out of.
        $payload = ['primary' => 'front', 'left' => 'port', 'count' => 2, 'nested' => ['deep' => true]];

        $labels = new Hydrator()->hydrate(Fixtures\OpenLabels::class, $payload);

        self::assertSame('front', $labels->primary);
        self::assertSame(['left', 'count', 'nested'], $labels->additionalProperties->keys());
        self::assertSame('port', $labels->additionalProperties->get('left'));
        self::assertSame(['deep' => true], $labels->additionalProperties->get('nested'));

        // Beside, not nested, unchanged, and in a stable order.
        self::assertSame($payload, new Marshaller()->marshal($labels));
    }

    public function testTheBagNeverShadowsADeclaredProperty(): void
    {
        // `primary` is declared, so its key goes to that property and nowhere
        // else — never to the property and the bag both, which would marshal
        // one wire key out of two sources.
        $labels = new Hydrator()->hydrate(Fixtures\OpenLabels::class, ['primary' => 'front', 'left' => 'port']);

        self::assertSame('front', $labels->primary);
        self::assertFalse($labels->additionalProperties->has('primary'));
        self::assertSame(['left'], $labels->additionalProperties->keys());
    }

    public function testADeclaredPropertyTheBagCouldHaveClaimedStaysClaimedWhenAbsent(): void
    {
        // The key is claimed by its descriptor whether or not the payload
        // carried it, so an absent declared property does not leave its name
        // free for the bag to pick up from some other payload shape.
        $labels = new Hydrator()->hydrate(Fixtures\OpenLabels::class, ['left' => 'port']);

        self::assertNull($labels->primary);
        self::assertSame(['left'], $labels->additionalProperties->keys());
        self::assertSame(['primary' => null, 'left' => 'port'], new Marshaller()->marshal($labels));
    }

    public function testAPayloadWithNoExtrasLeavesAnEmptyBagRatherThanNothing(): void
    {
        $labels = new Hydrator()->hydrate(Fixtures\OpenLabels::class, ['primary' => 'front']);

        self::assertTrue($labels->additionalProperties->isEmpty());
        self::assertSame(['primary' => 'front'], new Marshaller()->marshal($labels));
    }

    public function testAnExtraKeyInTheBagCannotOverwriteADeclaredWireNameEither(): void
    {
        // The hand-constructed counterpart of the shadowing test above: even
        // when a bag is built with a declared wire name in it, the declared
        // property owns that key on the way out.
        $labels = new Fixtures\OpenLabels('front', new AdditionalPropertyBag(['primary' => 'smuggled', 'left' => 'port']));

        self::assertSame(['primary' => 'front', 'left' => 'port'], new Marshaller()->marshal($labels));
    }
}
