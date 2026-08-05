<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

final readonly class Tag
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
    ) {}
}
