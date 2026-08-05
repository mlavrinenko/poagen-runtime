<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\Contract\ApiErrorInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Stands in for the `Generated\ApiException` every poagen client carries
 * (`error-response-policy` keeps that class in the emitted tree, one per
 * profile, never in the runtime). {@see HttpTransport} only ever receives it
 * as a `class-string<ApiErrorInterface>` — never as a class the runtime
 * itself declares — so the runtime's own tests need one to construct, and
 * hand-writing it here rather than importing the generator's committed
 * petstore fixture (`tests/fixtures/generated/petstore/Generated/
 * ApiException.php`) is the whole point of
 * `bundle-the-runtime-package-with-its-own-ci-tooling`: this package's tests
 * run — and must keep running — with the generator absent, so nothing under
 * `runtime/` may import a class the generator emits. The body is identical to
 * every profile's copy because `ApiErrorInterface`'s contract leaves no other
 * way to satisfy it.
 */
final class ApiException extends RuntimeException implements ApiErrorInterface
{
    public function __construct(
        private readonly int $statusCode,
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly string $reason,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
