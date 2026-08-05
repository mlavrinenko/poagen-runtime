<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

final readonly class Dog
{
    public function __construct(
        public ?string $petType = null,
        public ?string $bark = null,
    ) {}
}
