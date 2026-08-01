<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Mlavrinenko\Paogen\Runtime\Attribute\Discriminator;

/**
 * One Model property's wire <-> PHP mapping. Built once per class by
 * {@see DescriptorParser} and cached for the process. See ADR-010.
 */
final readonly class PropertyDescriptor
{
    public function __construct(
        public string $phpName,
        public string $wireName,
        public PropertyType $type,
        public ?Discriminator $discriminator = null,
    ) {}
}
