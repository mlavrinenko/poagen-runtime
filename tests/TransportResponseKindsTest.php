<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Attribute\BranchSelector;
use Poagen\Runtime\Contract\Endpoint;
use Poagen\Runtime\Contract\ResponseType;
use Poagen\Runtime\Tests\Fixtures\Cat;
use Poagen\Runtime\Tests\Fixtures\Dog;
use Poagen\Runtime\Tests\Fixtures\Email;
use Poagen\Runtime\Tests\Fixtures\HttpTransportFixture;
use Poagen\Runtime\Tests\Fixtures\NoWholeReadStream;
use Poagen\Runtime\Tests\Fixtures\Pet;

/**
 * The six shapes a schema produces, decoded by the runtime rather than by
 * thirty-six copies of the same emitted branch. `KIND_ENVELOPE` is
 * {@see EnvelopeTransportTest}'s own file, split out to keep both under the
 * linecop limit (`quality-gates-at-maximum-strictness`) — the two share
 * {@see HttpTransportFixture} rather than each keeping its own copy of the
 * transport builder.
 *
 * Decodes into this package's own {@see Pet}, {@see Cat} and {@see Dog}
 * fixtures rather than any profile's emitted models — `ResponseType` itself
 * does not care whose class it is handed, only that hydration follows the
 * same rules either way, which is the whole reason a bare `ResponseType`
 * call is portable where {@see \Poagen\Generator\Tests\Integration\ResponseUnionSelectionTest}
 * (driven through an emitted `union-response` client, and therefore kept in
 * the generator's own test tree — see that class's own comment) is not.
 */
final class TransportResponseKindsTest extends TestCase
{
    use HttpTransportFixture;

    public function testNothingReadsNoBodyAtAll(): void
    {
        $transport = $this->transport(new Response(204, [], ''));

        self::assertNull($transport->send(Endpoint::delete('/pet/1'), ResponseType::nothing()));
    }

    public function testAnObjectIsHydratedIntoTheNamedModel(): void
    {
        $transport = $this->transport(
            new Response(200, ['Content-Type' => 'application/json'], '{"name":"Rex","photoUrls":["a"],"id":7}'),
        );

        $pet = $transport->send(Endpoint::get('/pet/7'), ResponseType::object(Pet::class));

        self::assertSame('Rex', $pet->name);
        self::assertSame(7, $pet->id);
        self::assertSame(['a'], $pet->photoUrls);
    }

    public function testAListHydratesEveryItemAndKeepsListKeys(): void
    {
        $transport = $this->transport(new Response(
            200,
            ['Content-Type' => 'application/json'],
            '[{"name":"Rex","photoUrls":[]},{"name":"Mia","photoUrls":[]}]',
        ));

        $pets = $transport->send(Endpoint::get('/pet/findByStatus'), ResponseType::listOf(Pet::class));

        self::assertCount(2, $pets);
        self::assertSame('Rex', $pets[0]->name);
        self::assertSame('Mia', $pets[1]->name);
    }

    public function testAScalarIsReadOffTheBodyWithoutDecoding(): void
    {
        self::assertSame(
            'session-token',
            $this->transport(new Response(200, [], 'session-token'))
                ->send(Endpoint::get('/user/login'), ResponseType::string()),
        );
        self::assertSame(
            42,
            $this->transport(new Response(200, [], '42'))
                ->send(Endpoint::get('/count'), ResponseType::int()),
        );
        self::assertSame(
            1.5,
            $this->transport(new Response(200, [], '1.5'))
                ->send(Endpoint::get('/ratio'), ResponseType::float()),
        );
        self::assertTrue(
            $this->transport(new Response(200, [], '1'))
                ->send(Endpoint::get('/flag'), ResponseType::bool()),
        );
    }

    /**
     * `a-binary-body-is-a-psr-7-stream`, response side: the stream is the
     * value, handed back exactly as the transport produced it — not the
     * request side's `NoWholeReadStream` fixture reused verbatim, because
     * both directions make the identical promise (`__toString()` and
     * `getContents()` both throw; everything a chunked reader needs
     * delegates normally). Identity, not equality, is what proves it: a
     * `(string) $response->getBody()` anywhere on this path would have
     * thrown getting here, rather than silently reading the body whole.
     */
    public function testABinaryResponseHandsBackTheStreamWithoutReadingIt(): void
    {
        $stream = new NoWholeReadStream(new Psr17Factory()->createStream('%PDF-1.4 bytes'));
        $transport = $this->transport(new Response(200, ['Content-Type' => 'application/pdf'], '')->withBody($stream));

        $result = $transport->send(Endpoint::get('/invoices/1/pdf'), ResponseType::stream());

        self::assertSame($stream, $result);
        self::assertSame(0, $result->tell());
    }

    public function testAMapIsDecodedAndHandedBackWithoutHydration(): void
    {
        $transport = $this->transport(
            new Response(200, ['Content-Type' => 'application/json'], '{"available":7,"sold":2}'),
        );

        self::assertSame(
            ['available' => 7, 'sold' => 2],
            $transport->send(Endpoint::get('/store/inventory'), ResponseType::mapOf(ResponseType::int())),
        );
    }

    public function testAnUnconstrainedObjectComesBackAsTheDecodedBody(): void
    {
        $transport = $this->transport(
            new Response(200, ['Content-Type' => 'application/json'], '{"anything":[1,2]}'),
        );

        self::assertSame(
            ['anything' => [1, 2]],
            $transport->send(Endpoint::get('/opaque'), ResponseType::mapOf(ResponseType::mixed())),
        );
    }

    public function testAScalarListIsDecodedWithoutHydratingAnything(): void
    {
        $transport = $this->transport(
            new Response(200, ['Content-Type' => 'application/json'], '["Actionscript","Ada","Agda"]'),
        );

        $templates = $transport->send(
            Endpoint::get('/gitignore/templates'),
            ResponseType::scalarListOf(ResponseType::string()),
        );

        // A list of strings, keyed 0..n. No model class was named and none
        // was needed — that is the whole shape the document declared.
        self::assertSame(['Actionscript', 'Ada', 'Agda'], $templates);
    }

    public function testADateFormattedListItemArrivesAsADateNotAString(): void
    {
        $transport = $this->transport(
            new Response(200, ['Content-Type' => 'application/json'], '["2022-11-28","2022-08-09"]'),
        );

        $versions = $transport->send(
            Endpoint::get('/versions'),
            ResponseType::scalarListOf(ResponseType::dateTime()),
        );

        self::assertCount(2, $versions);
        self::assertInstanceOf(DateTimeImmutable::class, $versions[0]);
        self::assertSame('2022-11-28', $versions[0]->format('Y-m-d'));
        self::assertSame('2022-08-09', $versions[1]->format('Y-m-d'));
    }

    public function testAListItemBoundToAValueObjectRoundTripsThroughFromScalar(): void
    {
        $transport = $this->transport(
            new Response(200, ['Content-Type' => 'application/json'], '["a@example.test","b@example.test"]'),
        );

        $emails = $transport->send(
            Endpoint::get('/emails'),
            ResponseType::scalarListOf(ResponseType::binding(Email::class)),
        );

        self::assertCount(2, $emails);
        self::assertInstanceOf(Email::class, $emails[0]);
        self::assertSame('a@example.test', $emails[0]->value);
    }

    /**
     * `ResponseType::union()` directly, sixth of the shapes this class
     * proves and the one `a-union-response-selects-its-branch` added.
     * {@see ResponseUnionSelectionTest}
     * drives the same runtime through the emitted `union-response` fixture;
     * this pins the bare {@see ResponseType} contract on its own, the way
     * every other kind above already is.
     */
    public function testAUnionResponseIsHydratedIntoTheBranchItsPresentFieldNames(): void
    {
        $transport = $this->transport(
            new Response(200, ['Content-Type' => 'application/json'], '{"bark":"woof"}'),
        );

        $dog = $transport->send(
            Endpoint::get('/creatures'),
            ResponseType::union(null, new BranchSelector(['bark' => Dog::class, 'meow' => Cat::class]), 'creature'),
        );

        self::assertInstanceOf(Dog::class, $dog);
        self::assertSame('woof', $dog->bark);
    }
}
