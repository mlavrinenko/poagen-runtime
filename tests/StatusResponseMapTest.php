<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Contract\ResponseType;
use Poagen\Runtime\Contract\StatusResponseMap;

/**
 * typed-error-responses: OpenAPI's own status precedence, proved directly
 * against the runtime matcher rather than only through a generated client.
 *
 * No real document this project has vendored (petstore, github, digitalocean)
 * declares a range key — every one of them sticks to exact statuses and
 * `default`. This is the synthetic fixture the settled implementation plan
 * says the precedence rule needs, or it ships unproven.
 */
final class StatusResponseMapTest extends TestCase
{
    public function testAnExactStatusWinsOverARangeAndOverDefault(): void
    {
        $exact = ResponseType::string();
        $map = new StatusResponseMap(
            exact: [['404', $exact]],
            ranges: [['4XX', ResponseType::int()]],
            default: ResponseType::bool(),
        );

        self::assertSame($exact, $map->match(404));
    }

    public function testARangeWinsOverDefaultWhenNoExactStatusMatches(): void
    {
        $range = ResponseType::int();
        $map = new StatusResponseMap(
            exact: [['404', ResponseType::string()]],
            ranges: [['4XX', $range]],
            default: ResponseType::bool(),
        );

        self::assertSame($range, $map->match(429));
    }

    public function testDefaultAppliesOnlyWhenNeitherAnExactNorARangeMatches(): void
    {
        $default = ResponseType::bool();
        $map = new StatusResponseMap(
            exact: [['404', ResponseType::string()]],
            ranges: [['4XX', ResponseType::int()]],
            default: $default,
        );

        self::assertSame($default, $map->match(503));
    }

    public function testTheRangeKeyIsDerivedFromTheStatussLeadingDigit(): void
    {
        $client4xx = ResponseType::int();
        $server5xx = ResponseType::float();
        $map = new StatusResponseMap(ranges: [
            ['4XX', $client4xx],
            ['5XX', $server5xx],
        ]);

        self::assertSame($client4xx, $map->match(418));
        self::assertSame($server5xx, $map->match(503));
    }

    public function testAStatusMatchingNoExactNoRangeAndNoDefaultIsNull(): void
    {
        $map = new StatusResponseMap(exact: [['200', ResponseType::string()]]);

        self::assertNull($map->match(500));
    }

    public function testAnEmptyMapMatchesNothing(): void
    {
        self::assertNull(new StatusResponseMap()->match(200));
    }

    /**
     * The emitter writes `['400', null]` rather than
     * `['400', ResponseType::nothing()]` for a declared status with no
     * body — shorter, and safe only because the pair's presence in `$exact`
     * or `$ranges` already says the status is declared. `match()` must
     * still hand back a real `ResponseType`, never the `null` the
     * constructor was given, so `null` keeps meaning exactly one thing on
     * this path: the status is not in the map at all.
     */
    public function testANullPairMeansDeclaredWithNoBodyAndStillMatchesToARealResponseType(): void
    {
        $map = new StatusResponseMap(
            exact: [['204', null]],
            ranges: [['5XX', null]],
        );

        $exact = $map->match(204);
        self::assertNotNull($exact);
        self::assertSame(ResponseType::KIND_NOTHING, $exact->kind);

        $range = $map->match(503);
        self::assertNotNull($range);
        self::assertSame(ResponseType::KIND_NOTHING, $range->kind);

        // The one status this map was never told about at all — still null,
        // not the declared-empty ResponseType a `null` pair normalises to.
        self::assertNull($map->match(200));
    }
}
