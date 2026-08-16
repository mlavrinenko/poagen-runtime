<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\ScalarBinding;

/**
 * One model carrying both halves of the binding question: a
 * {@see WallClock}, which asks for the consumer's context, and an
 * {@see Email}, a plain {@see ScalarBinding} that does not.
 * Having them on the same model is what proves the second is untouched by
 * the first.
 */
final readonly class Shift
{
    public function __construct(
        public ?WallClock $startsAt = null,
        public ?Email $owner = null,
    ) {}
}
