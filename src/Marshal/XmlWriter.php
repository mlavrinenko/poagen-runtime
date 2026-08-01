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
 */
final readonly class XmlWriter implements XmlWriterInterface
{
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
        self::appendMap($document, $root, $data);

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
            self::appendMap($document, $element, $item);
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
    private static function appendMap(DOMDocument $document, DOMElement $parent, array $data): void
    {
        foreach ($data as $name => $value) {
            self::appendValue($document, $parent, (string) $name, $value);
        }
    }

    private static function appendValue(DOMDocument $document, DOMElement $parent, string $name, mixed $value): void
    {
        // An absent optional property is absent from the document rather than an
        // empty element: `<price/>` and "no price" mean different things to a
        // parser, and the marshalled null means the second.
        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            self::appendArray($document, $parent, $name, $value);

            return;
        }

        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode(self::scalar($value)));
        $parent->appendChild($element);
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function appendArray(DOMDocument $document, DOMElement $parent, string $name, array $value): void
    {
        if (array_is_list($value)) {
            // Unwrapped, per the OpenAPI default: the element repeats.
            foreach ($value as $item) {
                self::appendValue($document, $parent, $name, $item);
            }

            return;
        }

        $element = $document->createElement($name);
        $parent->appendChild($element);
        self::appendMap($document, $element, $value);
    }

    private static function scalar(mixed $value): string
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
