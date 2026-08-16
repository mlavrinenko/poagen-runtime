<?php

declare(strict_types=1);

namespace Poagen\Runtime;

/**
 * A {@see ScalarBinding} that wants to know what the consumer stated about
 * the installation — see {@see BindingContext} for what that is and why a
 * document cannot supply it.
 *
 * Opt-in, and extending `ScalarBinding` rather than replacing it is what
 * keeps it cheap. A binding that implements this still satisfies every
 * existing check — `Bindings::scalarBindingType()` asks
 * `is_a($fqcn, ScalarBinding::class, true)` — so the generator emits exactly
 * the same property type it emitted before, and no fixture, example or
 * consumer tree regenerates because a value object opted in.
 *
 * The plain methods stay declared, so a binding must still work with no
 * context at all. That is not ceremony: a consumer constructing a Model by
 * hand, a test, or any code path that never passes through a transport all
 * reach `fromScalar()`, and a binding whose only correct answer needs a
 * context would break there. Give the plain path a sensible default and let
 * the context override it.
 */
interface ContextualScalarBinding extends ScalarBinding
{
    public static function fromScalarIn(mixed $raw, BindingContext $context): self;

    public function toScalarIn(BindingContext $context): string|int|float|bool;
}
