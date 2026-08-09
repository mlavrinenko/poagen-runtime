<?php

declare(strict_types=1);

namespace Poagen\Runtime\Format;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Binds `format: uuid` to `ramsey/uuid`. Selected from a profile, never by
 * default:
 *
 * ```php
 * new Profile(
 *     // ...
 *     formats: ['uuid' => \Poagen\Runtime\Format\RamseyUuid::class],
 * );
 * ```
 *
 * {@see UuidBinding} carries why this is a candidate rather than a built-in,
 * and why the library value hangs off a wrapper.
 */
final readonly class RamseyUuid extends UuidBinding
{
    public function __construct(public UuidInterface $value) {}

    public static function fromScalar(mixed $raw): self
    {
        return new self(Uuid::fromString(self::requireString($raw)));
    }

    public function toScalar(): string
    {
        return $this->value->toString();
    }
}
