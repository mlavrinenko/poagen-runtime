<?php

declare(strict_types=1);

namespace Poagen\Runtime\Attribute;

use Attribute;
use Poagen\Runtime\Marshal\Hydrator;
use Poagen\Runtime\Marshal\Marshaller;

/**
 * Marks the one promoted property that carries a payload's extra keys — the
 * ones the schema allowed through `additionalProperties` without naming.
 *
 * A declared property is found by its wire name. Extra keys have no name to
 * look up, so the property that carries them is found by this marker instead:
 * {@see Hydrator} fills it with every key
 * no declared property claimed, and
 * {@see Marshaller} spreads it back out
 * beside them rather than nesting it under a key of its own.
 *
 * The generator emits this only where the document declared both `properties`
 * and a schema for the extra values. Both halves are the vendor's, so both
 * reach the consumer.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class AdditionalProperties {}
