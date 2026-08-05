<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 mock that returns a fixed response and records the last request.
 *
 * A copy of `tests/generated/RecordingClient.php`'s shape, not a shared
 * import: that one lives in the generator's own test tree and is unreachable
 * once `runtime/` is split off on its own
 * (`bundle-the-runtime-package-with-its-own-ci-tooling`). The class has no
 * generator dependency at all — three PSR interfaces and a property — so
 * duplicating it here costs nothing real and buys the runtime package tests
 * their independence.
 */
final class RecordingClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function __construct(private readonly ResponseInterface $response) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }
}
