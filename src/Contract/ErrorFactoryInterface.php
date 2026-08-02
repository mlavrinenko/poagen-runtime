<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Builds the exception a transport throws for a response the API considers
 * a failure.
 *
 * Separate from the transport so the failure policy is replaceable on its
 * own: the default builds one exception type carrying status, request and
 * response (`error-response-policy`), while a consumer who wants `NotFoundException` for a
 * 404 and `RateLimited` for a 429 implements this instead of reimplementing
 * request assembly to get there.
 *
 * Whether a status is a failure at all stays with the transport. This is
 * asked only once that has been decided.
 */
interface ErrorFactoryInterface
{
    public function errorFor(int $status, RequestInterface $request, ResponseInterface $response): Throwable;
}
