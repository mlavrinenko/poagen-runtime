<?php

declare(strict_types=1);

namespace Poagen\Runtime\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Poagen\Runtime\BindingContext;
use Poagen\Runtime\Contract\ApiErrorInterface;
use Poagen\Runtime\Contract\DescriptorParserInterface;
use Poagen\Runtime\Contract\Endpoint;
use Poagen\Runtime\Contract\Envelope;
use Poagen\Runtime\Contract\ErrorFactoryInterface;
use Poagen\Runtime\Contract\HydratorInterface;
use Poagen\Runtime\Contract\MarshallerInterface;
use Poagen\Runtime\Contract\ResponseType;
use Poagen\Runtime\Contract\StatusResponseMap;
use Poagen\Runtime\Contract\TransportInterface;
use Poagen\Runtime\Contract\XmlWriterInterface;
use Poagen\Runtime\Marshal\DescriptorParser;
use Poagen\Runtime\Marshal\Hydrator;
use Poagen\Runtime\Marshal\Marshaller;
use Poagen\Runtime\Marshal\XmlWriter;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

use function assert;
use function is_string;
use function sprintf;

/**
 * The transport a generated client gets when the consumer supplies nothing
 * of their own: PSR-18 out, PSR-7 in, everything else defaulted.
 *
 * Two arguments are required because only the consumer can supply them: the
 * base address of the API, and the profile's `Generated\ApiException`, which
 * `error-response-policy` keeps in the emitted tree and which the runtime
 * therefore cannot name for itself. Everything after that is optional and
 * defaults to a
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
 *
 * `$context` is the last argument and the only one that is not a collaborator
 * but a statement of fact: what the consumer knows about the installation
 * that the document could not say — see {@see BindingContext}. It reaches the
 * defaulted marshaller and hydrator only. A consumer who supplies either of
 * those themselves is telling the transport how marshalling works, so they
 * pass the context to their own instance instead; a decorator such as
 * {@see Marshal\OmitNullMarshaller} wraps a `Marshaller` the consumer built,
 * and that is the one to hand it to.
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
        ?BindingContext $context = null,
    ) {
        $descriptors ??= new DescriptorParser();
        $context ??= new BindingContext();
        $this->errors = is_string($errors) ? new ApiExceptionFactory($errors) : $errors;
        $this->requests = new PsrRequestBuilder(
            $baseUri,
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            $marshaller ?? new Marshaller($descriptors, $context),
            $xmlWriter ?? new XmlWriter(),
        );
        $this->decoder = new ResponseDecoder($hydrator ?? new Hydrator($descriptors, $context));
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

        if ($expected->kind === ResponseType::KIND_ENVELOPE) {
            return $this->envelope($request, $response, $expected);
        }

        return $this->decoder->decode($response, $expected);
    }

    /**
     * The status decides which declared body to hydrate, never whether to
     * throw (`typed-error-responses`): {@see StatusResponseMap::match()}
     * carries OpenAPI's own precedence, and only a status matching nothing
     * there — or a matched body that fails to hydrate — reaches
     * {@see ErrorFactoryInterface}.
     *
     * Deliberately untyped as `Envelope<mixed>` in its own signature — the
     * status match is a runtime decision, so no static type here could ever
     * be more than that anyway. Returning bare `mixed` natively is what lets
     * {@see send()} hand this straight back as its own template `T` with no
     * override: PHPStan accepts a `mixed`-typed expression against any
     * declared return type, and rejects the same handoff the moment either
     * side names a concrete type or a `@var`/`@return` tag asserts one — see
     * the `typed-error-responses` decision for the failed alternatives.
     *
     * @param ResponseType<mixed> $expected
     */
    private function envelope(RequestInterface $request, ResponseInterface $response, ResponseType $expected): mixed
    {
        $status = $response->getStatusCode();
        $statuses = $expected->statuses;
        assert($statuses instanceof StatusResponseMap);
        $matched = $statuses->match($status);

        if (!$matched instanceof ResponseType) {
            throw $this->errors->errorFor(
                $status,
                $request,
                $response,
                sprintf('no exact, range or default response declared for status %d', $status),
            );
        }

        try {
            $body = $this->decoder->decode($response, $matched);
        } catch (Throwable $e) {
            throw $this->errors->errorFor(
                $status,
                $request,
                $response,
                sprintf('the declared body for status %d failed to hydrate: %s', $status, $e->getMessage()),
            );
        }

        return new Envelope($status, $body, $response);
    }

    private function client(): ClientInterface
    {
        return $this->httpClient ??= Psr18ClientDiscovery::find();
    }
}
