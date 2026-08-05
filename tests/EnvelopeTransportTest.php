<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Contract\ApiErrorInterface;
use Poagen\Runtime\Contract\Endpoint;
use Poagen\Runtime\Contract\Envelope;
use Poagen\Runtime\Contract\ResponseType;
use Poagen\Runtime\Contract\StatusResponseMap;
use Poagen\Runtime\Tests\Fixtures\ApiException;
use Poagen\Runtime\Tests\Fixtures\HttpTransportFixture;
use Poagen\Runtime\Tests\Fixtures\Pet;
use Poagen\Runtime\Tests\Fixtures\RecordingClient;

/**
 * `KIND_ENVELOPE`, split out of {@see TransportResponseKindsTest} to keep
 * both files under the linecop limit (`quality-gates-at-maximum-strictness`)
 * — the two share {@see HttpTransportFixture} rather than each keeping its
 * own copy of the transport builder.
 *
 * Narrowed by the owner ruling behind `typed-error-responses`: the profile's
 * `ApiException` is thrown only for a status the envelope's
 * `StatusResponseMap` declares no exact, range or `default` entry for, or a
 * declared body that fails to hydrate — never for a declared status,
 * whatever the number. This file exercises that rule against this package's
 * own {@see ApiException} and {@see Pet} fixtures, never the generator's
 * emitted ones — see {@see ApiException}'s own comment for why.
 */
final class EnvelopeTransportTest extends TestCase
{
    use HttpTransportFixture;

    /**
     * The status matches an exact entry, so the wire is decoded — not
     * thrown — into an {@see Envelope}
     * carrying the status, the hydrated body and the PSR-7 response.
     */
    public function testAnEnvelopeMatchingAnExactStatusDecodesRatherThanThrows(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], '{"name":"Rex","photoUrls":[]}');
        $transport = $this->transport($response);

        $envelope = $transport->send(
            Endpoint::get('/pet/7'),
            ResponseType::envelope(new StatusResponseMap(exact: [['200', ResponseType::object(Pet::class)]])),
        );

        self::assertSame(200, $envelope->status);
        self::assertInstanceOf(Pet::class, $envelope->body);
        self::assertSame('Rex', $envelope->body->name);
        self::assertSame($response, $envelope->response);
    }

    /**
     * OpenAPI's own precedence — exact, then range, then `default` — proven
     * through the transport rather than only against the bare
     * {@see StatusResponseMap} unit: a status matching both an exact entry
     * and a range still decodes the exact one.
     */
    public function testAnExactStatusIsPreferredOverARangeThatWouldAlsoMatch(): void
    {
        $transport = $this->transport(new Response(404, [], ''));

        $envelope = $transport->send(
            Endpoint::get('/pet/7'),
            ResponseType::envelope(new StatusResponseMap(
                exact: [['404', ResponseType::string()]],
                ranges: [['4XX', ResponseType::int()]],
                default: ResponseType::bool(),
            )),
        );

        self::assertSame(404, $envelope->status);
        self::assertSame('', $envelope->body);
    }

    public function testAStatusTheEnvelopeDidNotDeclareThrowsTheProfilesApiException(): void
    {
        $http = new RecordingClient(
            new Response(404, ['Content-Type' => 'application/json'], '{"message":"not found"}'),
        );
        $transport = $this->transport(null, $http);
        $expected = ResponseType::envelope(new StatusResponseMap(exact: [['200', ResponseType::object(Pet::class)]]));

        try {
            $transport->send(Endpoint::get('/pet/999'), $expected);
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertStringContainsString('no exact, range or default response declared for status 404', $e->getReason());
            self::assertSame($http->lastRequest, $e->getRequest());
            self::assertSame(404, $e->getResponse()->getStatusCode());
            self::assertSame(
                'https://runtime-fixture.example/api/v3/pet/999',
                (string) $e->getRequest()->getUri(),
            );
        }
    }

    public function testTheFailureIsAlsoCatchableThroughTheRuntimeContract(): void
    {
        $http = new RecordingClient(
            new Response(429, ['Content-Type' => 'application/json'], '{"message":"slow down"}'),
        );
        $expected = ResponseType::envelope(new StatusResponseMap(exact: [['200', ResponseType::nothing()]]));

        try {
            $this->transport(null, $http)->send(Endpoint::get('/pet/1'), $expected);
            self::fail('expected an API error');
        } catch (ApiErrorInterface $e) {
            // One handler covers every poagen client in a project, without
            // naming any one profile's generated class.
            self::assertSame(429, $e->getStatusCode());
            self::assertStringContainsString('no exact, range or default response declared for status 429', $e->getReason());
            self::assertSame($http->lastRequest, $e->getRequest());
            self::assertSame(429, $e->getResponse()->getStatusCode());
        }
    }

    /**
     * A declared body that fails to hydrate is the second case an envelope
     * cannot honestly represent — the transport catches the hydration
     * failure and raises `ApiException` naming it, rather than letting the
     * hydrator's own exception leak through untyped.
     */
    public function testADeclaredBodyThatFailsToHydrateRaisesTheProfilesApiException(): void
    {
        $transport = $this->transport(new Response(200, ['Content-Type' => 'application/json'], 'not json'));

        try {
            $transport->send(
                Endpoint::get('/pet/7'),
                ResponseType::envelope(new StatusResponseMap(exact: [['200', ResponseType::object(Pet::class)]])),
            );
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(200, $e->getStatusCode());
            self::assertStringContainsString('declared body for status 200 failed to hydrate', $e->getReason());
        }
    }

}
