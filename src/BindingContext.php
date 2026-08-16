<?php

declare(strict_types=1);

namespace Poagen\Runtime;

use DateTimeImmutable;
use DateTimeZone;

use function is_a;

/**
 * What the consumer knows about the installation they are talking to and the
 * document could not state. Constructed once, per client instance, and handed
 * to {@see Marshal\Hydrator} and {@see Marshal\Marshaller} — never a global,
 * never mutable, so two clients in one process can disagree.
 *
 * Today it carries one fact, and that fact is the reason the class exists.
 * RFC 3339 requires an offset on a `date-time`, so a value that carries none
 * is off-spec — and PHP resolves such a string against the PROCESS timezone.
 * Left alone, the same payload therefore means different instants on a
 * developer's machine and in a UTC container, with nothing said on either.
 * Naming `$naiveZone` here replaces that ambient answer with a stated one.
 *
 * `$naiveZone` is deliberately narrow: it applies only where the vendor
 * supplied no offset. PHP's own constructor already behaves that way — a
 * string carrying `+05:00` keeps it and ignores the zone argument entirely —
 * so a document that mixes compliant and off-spec values needs no special
 * handling, and this can never overwrite an offset the vendor did state.
 *
 * Leaving it null keeps the previous behaviour exactly, which is what makes
 * this safe to add to an existing client: a consumer who states nothing is a
 * consumer whose output does not move.
 *
 * The two dispatch helpers below live here rather than in the hydrator and
 * the marshaller because both need the same answer — is this binding
 * context-aware or not — and a second copy of that question is a second place
 * to get it wrong.
 */
final readonly class BindingContext
{
    public function __construct(public ?DateTimeZone $naiveZone = null) {}

    /**
     * A wire string as an instant: resolved in the stated zone when the value
     * carries no offset of its own, and by the process timezone when nothing
     * was stated.
     */
    public function dateTime(string $value): DateTimeImmutable
    {
        return $this->naiveZone instanceof DateTimeZone
            ? new DateTimeImmutable($value, $this->naiveZone)
            : new DateTimeImmutable($value);
    }

    /**
     * Hydrate a binding, giving it this context when it asked for one.
     *
     * A plain {@see ScalarBinding} goes through the static `fromScalar()` it
     * has always had. Opting in is what {@see ContextualScalarBinding} is
     * for, and not opting in costs nothing.
     *
     * @param class-string<ScalarBinding> $class
     */
    public function hydrateBinding(string $class, mixed $raw): ScalarBinding
    {
        if (is_a($class, ContextualScalarBinding::class, true)) {
            return $class::fromScalarIn($raw, $this);
        }

        return $class::fromScalar($raw);
    }

    /**
     * The way back out, symmetric with {@see hydrateBinding()}: a binding
     * told about the zone on the way in reads the same answer on the way out,
     * so a round trip through the wire is stable.
     */
    public function scalarOf(ScalarBinding $binding): string|int|float|bool
    {
        return $binding instanceof ContextualScalarBinding
            ? $binding->toScalarIn($this)
            : $binding->toScalar();
    }
}
