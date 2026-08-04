<?php

declare(strict_types=1);

namespace Poagen\Runtime\Contract;

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
     * `$nestedContainers` is `Profile::$xmlElements`'s validated
     * `'<operationId>.item.<property>'` binding, scoped to `$itemElement` —
     * property wire name → repeated child element name, for a property
     * whose array value the vendor wants wrapped in one named container
     * instead of OpenAPI's unwrapped default. Every implementation — the
     * default and any substitute alike — is handed the same argument for
     * the same call, so which writer renders a body never depends on
     * whether the profile happened to name one of its properties. Empty by
     * default, so a body with no binding is unaffected.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string,string>       $nestedContainers
     */
    public function renderList(array $items, string $rootElement, string $itemElement, array $nestedContainers = []): string;
}
