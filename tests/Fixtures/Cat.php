<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

final readonly class Cat
{
    public function __construct(
        public ?string $petType = null,
        public ?string $meow = null,
    ) {}
}
