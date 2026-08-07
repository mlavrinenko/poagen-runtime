<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\Contract\MarshallerInterface;

/**
 * Test double that ignores the model and always returns a fixed array.
 *
 * Lets {@see OmitNullMarshallerTest} drive `OmitNullMarshaller::prune()`
 * with arbitrary map/list/null shapes directly, without needing a
 * fixture Model for every combination — `prune()` operates purely on the
 * already-marshaled array, so what produced that array is irrelevant to it.
 */
final readonly class FixedArrayMarshaller implements MarshallerInterface
{
    /**
     * @param array<array-key, mixed> $fixed
     */
    public function __construct(private array $fixed) {}

    /**
     * @return array<string, mixed>
     */
    public function marshal(object $model): array
    {
        /** @var array<string, mixed> $fixed */
        $fixed = $this->fixed;

        return $fixed;
    }
}
