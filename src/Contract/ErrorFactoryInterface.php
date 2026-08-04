<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Builds the exception a transport throws for a response an envelope cannot
 * honestly represent — a status the document declared no exact, range or
 * `default` entry for, or a declared body that failed to hydrate.
 *
 * Separate from the transport so the failure policy is replaceable on its
 * own: the default builds one exception type carrying status, request,
 * response and the reason (`error-response-policy`, narrowed by the owner
 * ruling behind `typed-error-responses`), while a consumer who wants a
 * distinct type per failure kind implements this instead of reimplementing
 * request assembly to get there.
 *
 * Whether a status matches the envelope at all stays with the transport.
 * This is asked only once that has been decided, and `$reason` says why.
 */
interface ErrorFactoryInterface
{
    public function errorFor(int $status, RequestInterface $request, ResponseInterface $response, string $reason): Throwable;
}
