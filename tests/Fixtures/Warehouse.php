<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

final readonly class Warehouse
{
    /**
     * @param array<string, Tag>|null $bins
     */
    public function __construct(
        public ?string $name = null,
        public ?array $bins = null,
    ) {}
}
