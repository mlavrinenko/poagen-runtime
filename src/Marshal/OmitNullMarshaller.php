<?php

declare(strict_types=1);

namespace Poagen\Runtime\Marshal;

use Poagen\Runtime\Contract\MarshallerInterface;

use function array_is_list;
use function array_values;
use function is_array;

/**
 * Opt-in decorator that drops null-valued map entries from an
 * already-marshaled body.
 *
 * {@see Marshaller} emits every declared property, so an unset optional goes
 * out as an explicit `null`. Some provider APIs reject a request body that
 * carries those nulls — an unset optional is expected to be absent, not
 * `null`. Required fields are never null, so pruning only ever removes unset
 * optionals; null items inside a list are left alone (list positions are not
 * "unset optionals", so a `null` there is data, not an absence).
 *
 * The map/list split is read off the array itself ({@see array_is_list}), so a
 * map whose keys happen to be sequential numeric strings ("0", "1", …) reads as
 * a list and keeps its nulls. That is not a hole this decorator opens: PHP
 * interns those keys as ints on the way in, and `json_encode` already emits
 * such a map as a JSON array before any pruning — the ambiguity belongs to the
 * whole marshalling pipeline, and no decorator can recover the distinction from
 * a PHP array alone.
 *
 * This must never be the default `Marshaller`: globally omitting nulls is
 * wrong for any API that distinguishes "absent" from "null" (JSON Merge
 * Patch, `PATCH` bodies that null out a field on purpose). Wrap it around the
 * inner marshaller only at the boundary of a provider that needs it.
 */
final readonly class OmitNullMarshaller implements MarshallerInterface
{
    public function __construct(private MarshallerInterface $inner) {}

    /**
     * @return array<string, mixed>
     */
    public function marshal(object $model): array
    {
        /** @var array<string, mixed> $pruned */
        $pruned = $this->prune($this->inner->marshal($model));

        return $pruned;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function prune(array $data): array
    {
        $isList = array_is_list($data);
        $out = [];

        foreach ($data as $key => $value) {
            // Drop unset optionals (null map entries); keep null list positions.
            if ($value === null && $isList === false) {
                continue;
            }

            $out[$key] = is_array($value) ? $this->prune($value) : $value;
        }

        return $isList ? array_values($out) : $out;
    }
}
