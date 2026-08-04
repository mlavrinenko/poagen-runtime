<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Marshal;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Mlavrinenko\Paogen\Runtime\Contract\XmlWriterInterface;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Renders a marshalled payload as XML, for an operation whose request body the
 * document declared `application/xml`.
 *
 * Runs after {@see Marshaller}, never instead of it. The marshaller already
 * resolved wire names, scalar bindings and date formats, so this sees a plain
 * nested array and does one job: turn it into elements. That split keeps XML from
 * needing its own copy of every serialisation decision, and keeps the two content
 * types honest about producing the same values.
 *
 * Element naming follows the OpenAPI defaults, because those are the only rules
 * the specification supplies:
 *
 *   - a property becomes an element of the same name;
 *   - an array is *unwrapped* — its element repeats once per item, which is what
 *     `xml.wrapped: false` means and what it defaults to;
 *   - a nested object becomes an element with children.
 *
 * What the specification does not supply is the name of the root element, or of
 * the repeated element for a body that is a bare array. Those are the caller's to
 * name, which is why they are constructor arguments rather than guesses: a wrong
 * root element produces XML the vendor's parser rejects, and rejecting it at
 * generation time with a question is cheaper than at runtime with a 400.
 *
 * A vendor can also want a nested array *wrapped* rather than unwrapped: one
 * container element (named after the property, the same default every other
 * property gets) holding several repeated children, instead of the property's
 * own name repeated as siblings. `$nestedContainers` is that opt-in, read only
 * for a property whose marshalled value is itself a list. It is keyed by the
 * tag name of the element the property sits directly inside — `renderObject`'s
 * `$rootElement`, each of `renderList`'s `$itemElement` repeats, or (recursively)
 * a container or item element this same map already named one level up — so one
 * property name can mean "unwrap" under one element and "wrap, repeating a
 * named child" under another, which is what lets two operations in the same
 * document reuse a property name (kilbil's `inside`) for two different repeated
 * children (`good`, `goodlist`). There is no code path that populates this from
 * `Profile::$xmlElements` automatically — `Endpoint::xml()`/`xmlList()` already
 * take exactly the arguments the specification leaves open, root and item, and
 * neither runtime/src/Contract nor runtime/src/Http changes for this — so a
 * consumer who wants nesting builds the map themselves from the profile's
 * `'<operationId>.item.<property>'` entries (the generator validates those
 * against the schema; it does not wire them anywhere) and passes it to this
 * constructor when they build their own `HttpTransport`. Empty by default, so
 * a body that needs no nesting renders exactly as it always has.
 */
final readonly class XmlWriter implements XmlWriterInterface
{
    /**
     * @param array<string, array<string,string>> $nestedContainers enclosing element tag name → (property wire
     *                                                               name → repeated child element name)
     */
    public function __construct(
        private array $nestedContainers = [],
    ) {}

    /**
     * Render a single object payload.
     *
     * @param array<string,mixed> $data
     */
    public function renderObject(array $data, string $rootElement): string
    {
        $document = $this->newDocument();
        $root = $document->createElement($rootElement);
        $document->appendChild($root);
        $this->appendMap($document, $root, $data);

        return (string) $document->saveXML();
    }

    /**
     * Render a list payload: one `$itemElement` per entry, inside `$rootElement`.
     *
     * @param list<array<string,mixed>> $items
     */
    public function renderList(array $items, string $rootElement, string $itemElement): string
    {
        $document = $this->newDocument();
        $root = $document->createElement($rootElement);
        $document->appendChild($root);

        foreach ($items as $item) {
            $element = $document->createElement($itemElement);
            $root->appendChild($element);
            $this->appendMap($document, $element, $item);
        }

        return (string) $document->saveXML();
    }

    private function newDocument(): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        // Byte-stability at the boundary: without this the output depends on
        // whatever whitespace libxml felt like, which makes a request body
        // impossible to diff in a test.
        $document->formatOutput = true;
        $document->preserveWhiteSpace = false;

        return $document;
    }

    /**
     * @param array<array-key,mixed> $data
     */
    private function appendMap(DOMDocument $document, DOMElement $parent, array $data): void
    {
        foreach ($data as $name => $value) {
            $this->appendValue($document, $parent, (string) $name, $value);
        }
    }

    private function appendValue(DOMDocument $document, DOMElement $parent, string $name, mixed $value): void
    {
        // An absent optional property is absent from the document rather than an
        // empty element: `<price/>` and "no price" mean different things to a
        // parser, and the marshalled null means the second.
        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            $this->appendArray($document, $parent, $name, $value);

            return;
        }

        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode($this->scalar($value)));
        $parent->appendChild($element);
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private function appendArray(DOMDocument $document, DOMElement $parent, string $name, array $value): void
    {
        if (array_is_list($value)) {
            $child = $this->nestedContainers[$parent->tagName][$name] ?? null;

            if (is_string($child) && $child !== '') {
                $this->appendNestedContainer($document, $parent, $name, $child, $value);

                return;
            }

            // Unwrapped, per the OpenAPI default: the element repeats.
            foreach ($value as $item) {
                $this->appendValue($document, $parent, $name, $item);
            }

            return;
        }

        $element = $document->createElement($name);
        $parent->appendChild($element);
        $this->appendMap($document, $element, $value);
    }

    /**
     * One `$name` container, holding one `$childElement` per entry — the
     * vendor's alternative to the unwrapped default, opted into per property
     * through `$nestedContainers`.
     *
     * @param list<mixed> $entries
     */
    private function appendNestedContainer(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        string $childElement,
        array $entries,
    ): void {
        $container = $document->createElement($name);
        $parent->appendChild($container);

        foreach ($entries as $entry) {
            $child = $document->createElement($childElement);
            $container->appendChild($child);
            $this->appendMap($document, $child, is_array($entry) ? $entry : []);
        }
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            // `true`/`false` rather than `1`/`0`: the same spelling
            // `json_encode` uses, so the two content types agree.
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException(
            'XML request bodies carry scalars, lists and maps; got ' . get_debug_type($value),
        );
    }
}
