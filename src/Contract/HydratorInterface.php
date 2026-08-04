<?php

declare(strict_types=1);

namespace Poagen\Runtime\Contract;

/**
 * Turns a decoded response body into a typed Model instance.
 *
 * The inverse of {@see MarshallerInterface}, and bound by the same rule: the
 * two must agree about wire names, date formats and scalar bindings, or a
 * value survives one direction and not the other. Replace both together.
 *
 * An implementation is handed the class it must build and the decoded array
 * to build it from, and is expected to recurse into nested Models, lists,
 * maps and unions. It may read whatever metadata it likes — the default
 * reads the constructor's phpdoc and the `#[WireName]`, `#[Format]` and
 * `#[Discriminator]` attributes — but nothing else in the runtime depends on
 * how it decides.
 */
interface HydratorInterface
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<mixed>    $data
     *
     * @return T
     */
    public function hydrate(string $class, array $data): object;
}
