<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Contract\Envelope;
use Poagen\Runtime\Contract\ResponseType;
use Poagen\Runtime\Contract\StatusResponseMap;

/**
 * typed-error-responses: what every generated operation returns now — the
 * status the wire sent, the body hydrated into the shape the document
 * declared for it, and the PSR-7 response both came from.
 */
final class EnvelopeTest extends TestCase
{
    public function testItCarriesTheStatusTheBodyAndTheResponseTogether(): void
    {
        $body = ['id' => 7];
        $response = new Response(200);

        $envelope = new Envelope(200, $body, $response);

        self::assertSame(200, $envelope->status);
        self::assertSame($body, $envelope->body);
        self::assertSame($response, $envelope->response);
    }

    public function testANullBodyIsAWellFormedEnvelopeNotAMissingOne(): void
    {
        $envelope = new Envelope(204, null, new Response(204));

        self::assertSame(204, $envelope->status);
        self::assertNull($envelope->body);
    }

    public function testResponseTypeEnvelopeCarriesTheStatusResponseMapItWasBuiltFrom(): void
    {
        $statuses = new StatusResponseMap(exact: [['200', ResponseType::string()]]);

        $type = ResponseType::envelope($statuses);

        self::assertSame(ResponseType::KIND_ENVELOPE, $type->kind);
        self::assertSame($statuses, $type->statuses);
    }
}
