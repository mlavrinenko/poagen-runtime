<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

/**
 * The one collaborator an emitted operation talks to.
 *
 * A generated method describes its request as an {@see Endpoint} and names
 * the shape it expects back as a {@see ResponseType}; everything between —
 * resolving the base address, building the PSR-7 message, sending it,
 * branching on the status and decoding the body — happens here. That is
 * what keeps an emitted operation a single call instead of fifteen lines
 * of the same sequence per endpoint.
 *
 * Implement this to replace the runtime wholesale: a fake that answers from
 * fixtures, a transport that talks to a queue instead of HTTP, or a
 * decorator that wraps the default one to add retries, caching or tracing.
 * No generated file needs to change for any of it — a client is constructed
 * with whatever implementation the consumer hands it.
 *
 * Implementations must:
 *   - send the request the {@see Endpoint} describes, unchanged in what it
 *     says (path, query, headers, body);
 *   - throw for a response the API considers a failure, rather than
 *     returning a half-value;
 *   - return exactly what the {@see ResponseType} promises, so the emitted
 *     method's declared return type stays true.
 */
interface TransportInterface
{
    /**
     * @template T
     *
     * @param ResponseType<T> $expected the shape the caller was promised
     *
     * @return T
     */
    public function send(Endpoint $endpoint, ResponseType $expected): mixed;
}
