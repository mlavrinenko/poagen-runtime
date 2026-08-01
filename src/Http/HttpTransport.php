<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Mlavrinenko\Paogen\Runtime\Contract\ApiErrorInterface;
use Mlavrinenko\Paogen\Runtime\Contract\DescriptorParserInterface;
use Mlavrinenko\Paogen\Runtime\Contract\Endpoint;
use Mlavrinenko\Paogen\Runtime\Contract\ErrorFactoryInterface;
use Mlavrinenko\Paogen\Runtime\Contract\HydratorInterface;
use Mlavrinenko\Paogen\Runtime\Contract\MarshallerInterface;
use Mlavrinenko\Paogen\Runtime\Contract\ResponseType;
use Mlavrinenko\Paogen\Runtime\Contract\TransportInterface;
use Mlavrinenko\Paogen\Runtime\Contract\XmlWriterInterface;
use Mlavrinenko\Paogen\Runtime\Marshal\DescriptorParser;
use Mlavrinenko\Paogen\Runtime\Marshal\Hydrator;
use Mlavrinenko\Paogen\Runtime\Marshal\Marshaller;
use Mlavrinenko\Paogen\Runtime\Marshal\XmlWriter;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function is_string;

/**
 * The transport a generated client gets when the consumer supplies nothing
 * of their own: PSR-18 out, PSR-7 in, everything else defaulted.
 *
 * Two arguments are required because only the consumer can supply them: the
 * base address of the API, and the profile's `Generated\ApiException`, which
 * ADR-009 keeps in the emitted tree and which the runtime therefore cannot
 * name for itself. Everything after that is optional and defaults to a
 * working implementation — the PSR client and factories through
 * `php-http/discovery`, and the marshalling machinery through its own
 * defaults — so wiring is opt-in rather than a tax:
 *
 * ```php
 * $api = new ApiClient(new HttpTransport('https://api.example.com', ApiException::class));
 * ```
 *
 * Any one of them can be replaced without touching the others, and the whole
 * class can be replaced by implementing {@see TransportInterface}.
 *
 * The PSR-18 client is discovered on first use rather than in the
 * constructor, so wiring a transport in a container never fails for an
 * application that has not sent a request yet.
 */
final class HttpTransport implements TransportInterface
{
    private readonly ErrorFactoryInterface $errors;

    private readonly PsrRequestBuilder $requests;

    private readonly ResponseDecoder $decoder;

    /**
     * @param class-string<ApiErrorInterface>|ErrorFactoryInterface $errors the profile's `Generated\ApiException`,
     *                                                                      or a policy that builds failures itself
     */
    public function __construct(
        string $baseUri,
        ErrorFactoryInterface|string $errors,
        private ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?MarshallerInterface $marshaller = null,
        ?HydratorInterface $hydrator = null,
        ?XmlWriterInterface $xmlWriter = null,
        ?DescriptorParserInterface $descriptors = null,
    ) {
        $descriptors ??= new DescriptorParser();
        $this->errors = is_string($errors) ? new ApiExceptionFactory($errors) : $errors;
        $this->requests = new PsrRequestBuilder(
            $baseUri,
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            $marshaller ?? new Marshaller($descriptors),
            $xmlWriter ?? new XmlWriter(),
        );
        $this->decoder = new ResponseDecoder($hydrator ?? new Hydrator($descriptors));
    }

    /**
     * @template T
     *
     * @param ResponseType<T> $expected
     *
     * @return T
     */
    public function send(Endpoint $endpoint, ResponseType $expected): mixed
    {
        $request = $this->requests->build($endpoint);
        $response = $this->client()->sendRequest($request);
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw $this->errors->errorFor($status, $request, $response);
        }

        return $this->decoder->decode($response, $expected);
    }

    private function client(): ClientInterface
    {
        return $this->httpClient ??= Psr18ClientDiscovery::find();
    }
}
