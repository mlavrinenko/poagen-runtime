<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Poagen\Runtime\Http\HttpTransport;

/**
 * The one {@see HttpTransport} every fixture-driven transport test in this
 * package builds: a fixed base address, this package's own {@see
 * ApiException} fixture (never the generator's — see that class's own
 * comment), and either a canned response or a caller-supplied
 * {@see RecordingClient} for a request assertion. Shared by
 * `TransportResponseKindsTest` and `EnvelopeTransportTest` — the same
 * response shapes, split across two files to keep both under the linecop
 * limit, need the identical transport under them rather than two copies of
 * it.
 *
 * A namesake of the generator's own `tests/Support/PetstoreHttpTransport`,
 * which this replaced in both files: same shape, renamed because nothing
 * here is petstore-specific any more — the base address is arbitrary, not a
 * profile's, now that the ApiException and Model classes it wires together
 * are this package's own fixtures rather than a committed golden tree.
 */
trait HttpTransportFixture
{
    private function transport(?Response $response, ?RecordingClient $http = null): HttpTransport
    {
        $factory = new Psr17Factory();
        $http ??= new RecordingClient($response ?? new Response(204, [], ''));

        return new HttpTransport(
            'https://runtime-fixture.example/api/v3',
            ApiException::class,
            $http,
            $factory,
            $factory,
        );
    }
}
