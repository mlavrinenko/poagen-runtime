<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Mlavrinenko\Paogen\Runtime\Http\HttpTransport;

/**
 * Every response one operation's document declared, matched by OpenAPI's own
 * precedence: an exact status code wins, then a range key such as `4XX`,
 * then `default`. {@see ResponseType::envelope()} carries one of these; the
 * default {@see HttpTransport} is the only reader — deciding which model to
 * hydrate is the only place the owner ruling behind `typed-error-responses`
 * admits a status-shaped rule, and it never decides whether to throw.
 *
 * A plain `array<string, ResponseType>` looks like the natural shape for
 * `$exact`, but a numeric-looking string key such as `'200'` is exactly what
 * PHP folds to an `int` at the array-literal level, and PHPStan follows that
 * fold when it types an emitted literal — a keyed map here would type-check
 * `['200' => ...]` as `array<int, ResponseType>` against a declared
 * `array<string, ResponseType>` and fail. A list of pairs sidesteps the fold
 * entirely: the status text is a value, never a key.
 */
final readonly class StatusResponseMap
{
    /**
     * @param list<array{0: string, 1: ResponseType<mixed>}> $exact   exact status text as the document
     *                                                                spells it — `'200'`, `'403'`
     * @param list<array{0: string, 1: ResponseType<mixed>}> $ranges  range keys as the document spells
     *                                                                them — `'4XX'`, `'5XX'`
     * @param ResponseType<mixed>|null                       $default the `default` entry, if the document declared one
     */
    public function __construct(
        public array $exact = [],
        public array $ranges = [],
        public ?ResponseType $default = null,
    ) {}

    /**
     * `null` means the wire sent a status this map has no exact, range or
     * `default` entry for — the one case {@see Envelope} cannot honestly
     * represent, and the caller raises rather than returning one anyway.
     *
     * @return ResponseType<mixed>|null
     */
    public function match(int $status): ?ResponseType
    {
        $text = (string) $status;

        foreach ($this->exact as [$candidate, $type]) {
            if ($candidate === $text) {
                return $type;
            }
        }

        // A three-digit HTTP status always yields a non-empty $text, so its
        // first character is always defined.
        $range = $text[0] . 'XX';

        foreach ($this->ranges as [$candidate, $type]) {
            if ($candidate === $range) {
                return $type;
            }
        }

        return $this->default;
    }
}
