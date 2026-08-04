<?php

declare(strict_types=1);

namespace Poagen\Runtime\Contract;

/**
 * Reads a Model class and reports how each of its properties maps to the
 * wire.
 *
 * The metadata source behind {@see HydratorInterface} and
 * {@see MarshallerInterface}: it is asked once per class, and both
 * directions read the same answer, which is why they cannot drift apart.
 *
 * The default reads native reflection, the constructor's `@param` phpdoc,
 * and the `#[WireName]`, `#[Format]` and `#[Discriminator]` attributes.
 * A consumer whose models carry their mapping somewhere else — a schema
 * registry, a config file, another library's attributes — implements this
 * and hands it to the hydrator and marshaller instead of describing the
 * mapping twice.
 *
 * Implementations are expected to cache: this is called for every request
 * body and every response.
 */
interface DescriptorParserInterface
{
    /**
     * @param class-string $class
     *
     * @return list<PropertyDescriptor>
     */
    public function descriptorsFor(string $class): array;
}
