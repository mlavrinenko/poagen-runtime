<?php

declare(strict_types=1);

namespace Poagen\Runtime\Contract;

/**
 * Turns a Model instance into the array a request body is built from.
 *
 * Runs before every body encoding, whatever the content type: JSON hands the
 * result to `json_encode`, XML hands the same result to
 * {@see XmlWriterInterface}. That order is deliberate — wire names, scalar
 * bindings and date formats are decided once, so two content types cannot
 * disagree about a value.
 *
 * The returned array is expected to be JSON-encodable: scalars, nulls,
 * lists and string-keyed arrays, nothing else.
 */
interface MarshallerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function marshal(object $model): array;
}
