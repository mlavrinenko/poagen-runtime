<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use LogicException;
use Psr\Http\Message\StreamInterface;

/**
 * A PSR-7 stream that refuses to be read whole.
 *
 * `__toString()` and `getContents()` are the two ways a body becomes a PHP
 * string the process has to hold, and both throw here. Everything else
 * delegates, so a client that reads in chunks — which is what a PSR-18
 * implementation does — works normally against it.
 *
 * This is the regression test's teeth for
 * represent-non-json-request-and-response-bodies: asserting that
 * `PsrRequestBuilder` returns the same stream object proves it did not copy
 * the body, but only a stream that throws proves nobody *reads* it. A `(string)`
 * added to that path in a year's time fails here with a message naming what
 * went wrong, rather than passing and quietly turning every upload into a
 * memory-sized one.
 */
final readonly class NoWholeReadStream implements StreamInterface
{
    private const string MESSAGE = 'the request body was read into memory whole';

    public function __construct(private StreamInterface $inner) {}

    public function __toString(): string
    {
        throw new LogicException(self::MESSAGE);
    }

    public function getContents(): string
    {
        throw new LogicException(self::MESSAGE);
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function detach(): mixed
    {
        return $this->inner->detach();
    }

    public function getSize(): ?int
    {
        return $this->inner->getSize();
    }

    public function tell(): int
    {
        return $this->inner->tell();
    }

    public function eof(): bool
    {
        return $this->inner->eof();
    }

    public function isSeekable(): bool
    {
        return $this->inner->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->inner->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->inner->rewind();
    }

    public function isWritable(): bool
    {
        return $this->inner->isWritable();
    }

    public function write(string $string): int
    {
        return $this->inner->write($string);
    }

    public function isReadable(): bool
    {
        return $this->inner->isReadable();
    }

    public function read(int $length): string
    {
        return $this->inner->read($length);
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $this->inner->getMetadata($key);
    }
}
