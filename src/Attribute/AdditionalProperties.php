<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Attribute;

use Attribute;

/**
 * Class-level: declares how a Model handles wire keys not declared in its
 * properties. `false` (the default) drops them; a class-string accepts them
 * and hydrates each value as that type.
 *
 * See ADR-010.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AdditionalProperties
{
    /**
     * @param class-string|false $valueClass
     */
    public function __construct(public string|false $valueClass = false) {}
}
