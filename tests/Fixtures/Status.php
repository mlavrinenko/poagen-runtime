<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

enum Status: string
{
    case Placed = 'placed';
    case Approved = 'approved';
    case Delivered = 'delivered';
}
