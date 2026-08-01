<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

/**
 * Renders a marshalled payload as an XML document.
 *
 * Runs after {@see MarshallerInterface}, never instead of it: what arrives
 * here is a plain nested array whose names and formats are already decided,
 * and the only job left is turning it into elements.
 *
 * The root element — and, for a list, the repeated element — are given
 * rather than guessed, because the OpenAPI specification supplies no default
 * for either. An implementation that invents one produces XML the vendor's
 * parser rejects.
 */
interface XmlWriterInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function renderObject(array $data, string $rootElement): string;

    /**
     * @param list<array<string, mixed>> $items
     */
    public function renderList(array $items, string $rootElement, string $itemElement): string;
}
