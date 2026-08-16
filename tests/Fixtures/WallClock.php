<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use DateTimeImmutable;
use DateTimeZone;
use Poagen\Runtime\BindingContext;
use Poagen\Runtime\ContextualScalarBinding;

use function assert;
use function is_string;

/**
 * A value object for a vendor that sends `2026-08-14 12:51:17` and never says
 * which zone that is — the shape {@see ContextualScalarBinding} exists for,
 * modelled on the kilbil loyalty API that produced the defect.
 *
 * Both plain methods still work, and that is the point of them: without a
 * context the value falls back to whatever the process decided, exactly as
 * every `ScalarBinding` did before. With one, the consumer's statement wins.
 */
final readonly class WallClock implements ContextualScalarBinding
{
    private const string WIRE = 'Y-m-d H:i:s';

    public function __construct(public DateTimeImmutable $value) {}

    public static function fromScalar(mixed $raw): self
    {
        return self::fromScalarIn($raw, new BindingContext());
    }

    public static function fromScalarIn(mixed $raw, BindingContext $context): self
    {
        assert(is_string($raw));

        return new self($context->dateTime($raw));
    }

    public function toScalar(): string
    {
        return $this->toScalarIn(new BindingContext());
    }

    public function toScalarIn(BindingContext $context): string
    {
        $zone = $context->naiveZone;
        $value = $zone instanceof DateTimeZone ? $this->value->setTimezone($zone) : $this->value;

        return $value->format(self::WIRE);
    }
}
