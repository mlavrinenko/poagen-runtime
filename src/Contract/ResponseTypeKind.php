<?php

declare(strict_types=1);

namespace Poagen\Runtime\Contract;

use Poagen\Runtime\Marshal\UnionBranchPicker;

/**
 * Every shape {@see ResponseType} can name.
 *
 * Split off the twelve `KIND_*` constants and their docblocks so a new one —
 * `KIND_STREAM` was the one that tipped it — stops crowding
 * {@see ResponseType} itself past the linecop limit
 * (`quality-gates-at-maximum-strictness`), the same reason
 * `ResponseAnalyzer`/`ResponseRefusal` and `RequestBodyAnalyzer`/
 * `RequestBodyRefusal` already split diagnostics off their analysers.
 * `ResponseType implements ResponseTypeKind`, so every existing
 * `ResponseType::KIND_*` reference resolves unchanged — PHP reads an
 * interface's constants as inherited on the implementing class, not merely
 * visible through it.
 */
interface ResponseTypeKind
{
    /** No body is read; the operation returns `void`. */
    public const string KIND_NOTHING = 'nothing';

    /** One Model, hydrated from the decoded body. */
    public const string KIND_OBJECT = 'object';

    /** A list of Models, each hydrated. */
    public const string KIND_LIST = 'list';

    /**
     * A list of scalars, decoded and converted item by item. No model class
     * is involved, which is what separates it from KIND_LIST.
     */
    public const string KIND_SCALAR_LIST = 'scalar_list';

    /** A date or date-time string, as a `\DateTimeImmutable`. */
    public const string KIND_DATETIME = 'datetime';

    /** A scalar wrapped by a `ScalarBinding` value object. */
    public const string KIND_SCALAR_BINDING = 'scalar_binding';

    /** A scalar, cast from the raw body without decoding. */
    public const string KIND_SCALAR = 'scalar';

    /** A map of values, decoded and returned as-is. */
    public const string KIND_MAP = 'map';

    /** A value the document left unconstrained: decoded and returned as-is. */
    public const string KIND_MIXED = 'mixed';

    /** A binary body: the PSR-7 stream the transport produced, never read. */
    public const string KIND_STREAM = 'stream';

    /**
     * A response whose whole body is one of several Models — hydrated into
     * whichever branch {@see UnionBranchPicker}
     * names, the same picker a union-typed property already uses. See
     * `a-union-response-selects-its-branch`.
     *
     * Nested inside a {@see ResponseType::mapOf()} or
     * {@see ResponseType::scalarListOf()} value the same way a scalar item
     * already can, which is how a response whose body is an array of union
     * items (`activity/list-stargazers-for-repo`) is expressed without a
     * sixth kind: a list whose item shape happens to be a union rather than
     * a bare scalar.
     */
    public const string KIND_UNION = 'union';

    /** The whole per-status resolution of one operation — {@see Envelope}. */
    public const string KIND_ENVELOPE = 'envelope';
}
