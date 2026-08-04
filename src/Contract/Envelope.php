<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use Psr\Http\Message\ResponseInterface;

/**
 * What every generated operation returns: the HTTP status the wire sent, the
 * body the document declared for that status, and the PSR-7 response that
 * carried both.
 *
 * The status no longer decides *whether* the call throws — it decides *what
 * shape `$body` hydrated into*, exactly the way a 200 always has. See the
 * owner ruling in `typed-error-responses`: "the status code does not change
 * the shape of a response, so it should not change the shape of the
 * return." `error-response-policy`'s `getDecodedBody(): mixed` is gone with
 * it — a declared body is a return value now, not something a caller
 * re-parses off an exception.
 *
 * A response the document did not describe at all — no exact, range or
 * `default` entry matches the wire status — still raises, and so does a
 * declared body that fails to hydrate: an envelope with an untyped body
 * would be lying about what the wire actually said, which is the one thing
 * an envelope cannot express honestly (fail-loud-never-silent).
 *
 * `$response` stays reachable so a consumer who needs a header, or the raw
 * body, has a way that does not involve a second transport call.
 *
 * @template-covariant T
 */
final readonly class Envelope
{
    /**
     * @param T $body hydrated into the shape the document declared for `$status`
     */
    public function __construct(
        public int $status,
        public mixed $body,
        public ResponseInterface $response,
    ) {}
}
