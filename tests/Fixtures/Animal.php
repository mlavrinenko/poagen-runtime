<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\Attribute\Discriminator;

final readonly class Animal
{
    public function __construct(
        #[Discriminator(field: 'petType', map: ['Dog' => Dog::class, 'Cat' => Cat::class])]
        public Dog|Cat|null $creature = null,
    ) {}
}
