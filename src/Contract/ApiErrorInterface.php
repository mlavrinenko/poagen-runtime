<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The contract the per-profile `Generated\ApiException` satisfies.
 *
 * ADR-009 keeps that exception in the emitted tree, so a project consuming
 * two vendors' APIs can tell one failure from the other by type. The runtime
 * still has to be able to construct it, which is what this declares: a
 * constructor the {@see ErrorFactoryInterface} can call and the accessors a
 * caller reads afterwards.
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
        string $message = '',
    );

    public function getStatusCode(): int;

    public function getRequest(): RequestInterface;

    public function getResponse(): ResponseInterface;

    /**
     * Best-effort JSON decode of the response body, or `null` when the body
     * is empty or not JSON.
     */
    public function getDecodedBody(): mixed;
}
