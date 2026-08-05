<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Poagen\Runtime\AdditionalPropertyBag;

use function count;
use function iterator_to_array;

/**
 * declared-shapes-become-types: the bag is a real object, not an array alias.
 *
 * `additionalProperties: true` is the one place a document allows values it
 * declares nothing about. Answering that with `array<string, mixed>` would
 * hand the consumer back the parsing job they came to be rid of, so the answer
 * is an object that can be asked questions. This pins the questions it
 * answers — the surface ships in the runtime package
 * (`runtime-ships-as-a-separate-package`), so it is public API and hard to
 * change later.
 */
final class AdditionalPropertyBagTest extends TestCase
{
    public function testAnEmptyBagIsTheDefaultAndKnowsItIsEmpty(): void
    {
        // The emitted property defaults to this, which is why a consumer never
        // has to guard before asking a question.
        $bag = new AdditionalPropertyBag();

        self::assertTrue($bag->isEmpty());
        self::assertCount(0, $bag);
        self::assertSame([], $bag->keys());
        self::assertSame([], $bag->toArray());
        self::assertFalse($bag->has('anything'));
    }

    public function testItAnswersPresenceSeparatelyFromValue(): void
    {
        // A key present with a null value and an absent key both read back as
        // null through `get()`, so presence needs its own question.
        $bag = new AdditionalPropertyBag(['nil' => null]);

        self::assertTrue($bag->has('nil'));
        self::assertNull($bag->get('nil'));
        self::assertFalse($bag->has('absent'));
        self::assertNull($bag->get('absent'));
    }

    public function testItReturnsAValueByKeyAndFallsBackToTheGivenDefault(): void
    {
        $bag = new AdditionalPropertyBag(['colour' => 'red', 'depth' => 3]);

        self::assertSame('red', $bag->get('colour'));
        self::assertSame(3, $bag->get('depth'));
        self::assertSame('fallback', $bag->get('absent', 'fallback'));
    }

    public function testItListsCountsAndIteratesInArrivalOrder(): void
    {
        // Order is the payload's, not the bag's. Keeping it is what makes a
        // hydrate/marshal round trip stable rather than merely lossless.
        $values = ['b' => 2, 'a' => 1, 'c' => 3];
        $bag = new AdditionalPropertyBag($values);

        self::assertSame(['b', 'a', 'c'], $bag->keys());
        self::assertSame(3, $bag->count());
        self::assertCount(3, $bag);
        self::assertSame($values, iterator_to_array($bag));
        self::assertSame($values, $bag->toArray());
    }

    public function testValuesArriveExactlyAsTheWireDeliveredThem(): void
    {
        // Nothing validates an extra value against anything: the document
        // declined to constrain it, so the bag declines to interpret it.
        $nested = ['deep' => ['deeper' => true]];
        $bag = new AdditionalPropertyBag(['raw' => $nested, 'zero' => 0, 'blank' => '']);

        self::assertSame($nested, $bag->get('raw'));
        self::assertSame(0, $bag->get('zero'));
        self::assertSame('', $bag->get('blank'));
        self::assertTrue($bag->has('zero'));
        self::assertTrue($bag->has('blank'));
    }

    public function testTheBagIsImmutableAndSharesNoStateWithWhatBuiltIt(): void
    {
        // Immutable like every other emitted value. The array is copied in by
        // value, so mutating the caller's copy afterwards cannot reach inside.
        $values = ['colour' => 'red'];
        $bag = new AdditionalPropertyBag($values);

        $values['colour'] = 'blue';
        $values['added'] = true;

        self::assertSame('red', $bag->get('colour'));
        self::assertFalse($bag->has('added'));
        self::assertSame(1, count($bag));
    }
}
