<?php

declare(strict_types=1);

namespace Poagen\Runtime\Attribute;

use Attribute;
use Poagen\Runtime\Marshal\UnionBranchException;

/**
 * Property-level: how to pick a branch of a PHP union type when the document
 * carries no tag to read. Sibling of {@see Discriminator}, which covers the
 * case where a tag exists.
 *
 * `$fields` maps a wire field name to the branch selected by that field's
 * mere presence. The generator only puts a field here when exactly one branch
 * declares it — a field one branch requires and no other branch declares at
 * all — so the map is a bijection and the presence of the key is the whole
 * test. Nothing is scored, ranked or preferred: see
 * `declare-an-undiscriminated-union-as-raw-data`.
 *
 * `$empty` names the branch a wholly empty payload selects, and is only ever
 * filled for a branch that declared `additionalProperties: false` with no
 * properties of its own. Such a branch accepts `{}` and nothing else, which
 * makes it decidable by the absence of fields where every other branch here is
 * decidable by their presence. GitHub's `empty-object` is the case, and is the
 * only branch schema of the fifty-two poagen measured across both large vendor
 * documents that declares a closed shape.
 *
 * A payload matching no branch, or more than one, raises
 * {@see UnionBranchException}. It is never
 * resolved by picking the first match, because a union that guesses is worse
 * than one that stops.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class BranchSelector
{
    /**
     * @param array<string, class-string> $fields wire field name => the branch its presence selects
     * @param class-string|null           $empty  branch selected by an empty payload, when one declared a closed shape
     */
    public function __construct(
        public array $fields,
        public ?string $empty = null,
    ) {}
}
