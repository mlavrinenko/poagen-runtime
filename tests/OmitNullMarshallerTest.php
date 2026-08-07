<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Marshal\Marshaller;
use Poagen\Runtime\Marshal\OmitNullMarshaller;
use Poagen\Runtime\Tests\Fixtures\FixedArrayMarshaller;
use Poagen\Runtime\Tests\Fixtures\Order;

/**
 * Direct unit coverage for {@see OmitNullMarshaller}'s prune semantics —
 * the opt-in decorator that drops null map entries a provider API rejects,
 * while leaving null list positions and the default {@see Marshaller} alone.
 */
final class OmitNullMarshallerTest extends TestCase
{
    public function testDropsNullMapEntries(): void
    {
        $inner = new FixedArrayMarshaller(['a' => 1, 'b' => null, 'c' => 'x']);
        $result = new OmitNullMarshaller($inner)->marshal(new Order());

        self::assertSame(['a' => 1, 'c' => 'x'], $result);
    }

    public function testDropsNestedNullMapEntries(): void
    {
        $inner = new FixedArrayMarshaller([
            'a' => ['x' => null, 'y' => 2],
            'b' => null,
        ]);
        $result = new OmitNullMarshaller($inner)->marshal(new Order());

        self::assertSame(['a' => ['y' => 2]], $result);
    }

    public function testKeepsNullInsideList(): void
    {
        $inner = new FixedArrayMarshaller(['items' => [1, null, 3]]);
        $result = new OmitNullMarshaller($inner)->marshal(new Order());

        self::assertSame(['items' => [1, null, 3]], $result);
    }

    public function testPrunesNullMapEntriesNestedInsideAListItem(): void
    {
        // A list keeps every position, including null ones, but each item
        // is still an ordinary array to recurse into — a map nested inside
        // a list item is pruned exactly like a top-level map would be.
        $inner = new FixedArrayMarshaller([
            'goods_data' => [
                ['sku' => 'a', 'flags' => null],
                ['sku' => 'b', 'flags' => ['gift' => true]],
            ],
        ]);
        $result = new OmitNullMarshaller($inner)->marshal(new Order());

        self::assertSame(
            [
                'goods_data' => [
                    ['sku' => 'a'],
                    ['sku' => 'b', 'flags' => ['gift' => true]],
                ],
            ],
            $result,
        );
    }

    public function testLeavesNonNullValuesUntouched(): void
    {
        $data = [
            'id' => 7,
            'name' => 'Ada',
            'active' => false,
            'tags' => ['a', 'b', 'c'],
            'meta' => ['role' => 'admin', 'level' => 3],
        ];
        $inner = new FixedArrayMarshaller($data);
        $result = new OmitNullMarshaller($inner)->marshal(new Order());

        self::assertSame($data, $result);
    }

    public function testRealMarshallerOmitsUnsetOptionalButKeepsSetOne(): void
    {
        $marshaller = new OmitNullMarshaller(new Marshaller());

        $unset = $marshaller->marshal(new Order(id: 5));
        self::assertSame(['id' => 5], $unset);
        self::assertArrayNotHasKey('pet_id', $unset);

        $set = $marshaller->marshal(new Order(id: 5, petId: 42));
        self::assertSame(['id' => 5, 'pet_id' => 42], $set);
    }
}
