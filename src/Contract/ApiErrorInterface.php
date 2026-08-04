<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The contract the per-profile `Generated\ApiException` satisfies.
 *
 * `error-response-policy` keeps that exception in the emitted tree, so a project consuming
 * two vendors' APIs can tell one failure from the other by type. The runtime
 * still has to be able to construct it, which is what this declares: a
 * constructor the {@see ErrorFactoryInterface} can call and the accessors a
 * caller reads afterwards.
 *
 * The owner ruling behind `typed-error-responses` narrows what this is
 * thrown for: a declared response is a return value now, hydrated onto an
 * `Envelope`, so this survives only for a status the document did not
 * describe at all, or a declared body that failed to hydrate. There is no
 * `getDecodedBody(): mixed` any more — nothing here ever decoded a body — and
 * `getReason()` replaces it, carrying why the transport raised rather than a
 * best-effort re-parse of the wire.
 *
 * Catching this instead of the generated class works too, and is the way to
 * write one handler that covers every paogen client in a project.
 */
interface ApiErrorInterface extends Throwable
{
    public function __construct(
        int $statusCode,
        RequestInterface $request,
        ResponseInterface $response,
        string $reason,
        string $message = '',
    );

    public function getStatusCode(): int;

    public function getRequest(): RequestInterface;

    public function getResponse(): ResponseInterface;

    /**
     * Why the transport raised: the status matched nothing the document
     * declared, or matched a declared response whose body failed to
     * hydrate.
     */
    public function getReason(): string;
}
