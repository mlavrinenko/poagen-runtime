<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Generator\Tests\Integration\XmlRequestBodyTest;

/**
 * Mirrors `gadget_by_weight` in `union-request-body-3.0.yaml` — one branch
 * of a `oneOf` request body union — the way {@see Contact} mirrors kilbil's
 * `contact` schema for {@see XmlRequestBodyTest}.
 */
final readonly class GadgetByWeight
{
    public function __construct(public float $weight) {}
}
