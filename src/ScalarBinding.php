<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime;

/**
 * Opt-in contract that makes a `#[BindsFormat]` value object transparent at
 * the wire boundary. See `hydration-and-serialization-contract`.
 *
 * A VO implementing this interface is treated as a thin wrapper around a
 * scalar: generated models route its value through `toScalar()` on the way
 * out (serialization / request body marshalling) and through `fromScalar()`
 * on the way in (hydration from JSON payloads). VOs that do not implement
 * this interface fall back to whole-object JSON (de)serialization — that
 * path is reserved for complex-format bindings (e.g. an `Address` VO).
 */
interface ScalarBinding
{
    public static function fromScalar(mixed $raw): self;

    public function toScalar(): string|int|float|bool;
}
