<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Mlavrinenko\Paogen\Runtime\Attribute\BranchSelector;
use Mlavrinenko\Paogen\Runtime\Attribute\Discriminator;

/**
 * One Model property's wire <-> PHP mapping. Built once per class by
 * {@see DescriptorParser} and cached for the process. See
 * `attribute-and-phpdoc-driven-serialization`.
 */
final readonly class PropertyDescriptor
{
    /**
     * @param bool                $additional true for the one property carrying the keys no
     *                                        declared property claims — the schema allowed
     *                                        them through `additionalProperties` without
     *                                        naming them, so there is no wire name to look
     *                                        one up by and $wireName is not read for it
     * @param BranchSelector|null $selector   how to pick a union branch when no tag exists;
     *                                        mutually exclusive with $discriminator, and the
     *                                        two together are the only ways a union-typed
     *                                        property ever names a branch
     *                                        (`declare-an-undiscriminated-union-as-raw-data`)
     */
    public function __construct(
        public string $phpName,
        public string $wireName,
        public PropertyType $type,
        public ?Discriminator $discriminator = null,
        public bool $additional = false,
        public ?BranchSelector $selector = null,
    ) {}
}
