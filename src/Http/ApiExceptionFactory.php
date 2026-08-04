<?php

declare(strict_types=1);

namespace Poagen\Runtime\Http;

use Poagen\Runtime\Contract\ApiErrorInterface;
use Poagen\Runtime\Contract\ErrorFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The default failure policy: build the profile's own `ApiException`.
 *
 * `error-response-policy` keeps that class in the emitted tree so two vendors' clients in
 * one project throw distinguishable types. The runtime cannot know which
 * class that is, so it is named once — where the transport is wired — and
 * this turns it into an instance.
 */
final readonly class ApiExceptionFactory implements ErrorFactoryInterface
{
    /**
     * @param class-string<ApiErrorInterface> $exceptionClass the profile's `Generated\ApiException`
     */
    public function __construct(private string $exceptionClass) {}

    public function errorFor(int $status, RequestInterface $request, ResponseInterface $response, string $reason): Throwable
    {
        $class = $this->exceptionClass;

        return new $class($status, $request, $response, $reason);
    }
}
