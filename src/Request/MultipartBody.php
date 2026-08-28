<?php

declare(strict_types=1);

namespace JOOservices\Client\Request;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use Psr\Http\Message\StreamInterface;

final class MultipartBody
{
    private const DEFAULT_FILENAME = 'blob';

    private const DEFAULT_FILE_TYPE = 'application/octet-stream';

    public readonly string $boundary;

    /** @var list<string|resource|StreamInterface> */
    public readonly array $chunks;

    public readonly bool $seekable;

    public readonly ?int $size;

    /**
     * @param list<MultipartPart|array<string, mixed>> $parts
     */
    public function __construct(array $parts)
    {
        if ($parts === []) {
            throw new InvalidConfigurationException('Multipart requests require at least one part.');
        }

        $this->boundary = bin2hex(random_bytes(16));
        $this->chunks = $this->frame($parts);
        $this->seekable = $this->chunksAreSeekable($this->chunks);
        $this->size = $this->totalSize($this->chunks);
    }

    /**
     * @param list<MultipartPart|array<string, mixed>> $parts
     * @return list<string|resource|StreamInterface>
     */
    private function frame(array $parts): array
    {
        $chunks = [];
        foreach ($parts as $part) {
            $normalized = $this->normalize($part);
            $chunks[] = $this->prefix($normalized);
            $chunks[] = $normalized->contents;
            $chunks[] = "\r\n";
        }

        $chunks[] = '--' . $this->boundary . "--\r\n";

        return $chunks;
    }

    private function normalize(mixed $part): MultipartPart
    {
        if ($part instanceof MultipartPart) {
            return $part;
        }

        if (! is_array($part)) {
            throw new InvalidConfigurationException('Each multipart part must be a MultipartPart or an array.');
        }

        return new MultipartPart(
            $this->requiredString($part, 'name', 'Each multipart part requires a string name.'),
            $this->contents($part),
            $this->optionalString($part, 'filename', 'Multipart filename must be a string.'),
            $this->optionalString($part, 'contentType', 'Multipart content type must be a string.'),
            $this->headers($part),
        );
    }

    /**
     * @param array<mixed, mixed> $part
     */
    private function requiredString(array $part, string $key, string $message): string
    {
        $value = $part[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidConfigurationException($message);
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $part
     */
    private function optionalString(array $part, string $key, string $message): ?string
    {
        if (! array_key_exists($key, $part) || $part[$key] === null) {
            return null;
        }

        $value = $part[$key];
        if (! is_string($value)) {
            throw new InvalidConfigurationException($message);
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $part
     * @return string|resource|StreamInterface
     */
    private function contents(array $part): mixed
    {
        if (! array_key_exists('contents', $part)) {
            throw new InvalidConfigurationException('Each multipart part requires contents.');
        }

        $contents = $part['contents'];
        if (! is_string($contents) && ! is_resource($contents) && ! $contents instanceof StreamInterface) {
            throw new InvalidConfigurationException('Multipart contents must be a string, stream resource, or PSR-7 stream.');
        }

        return $contents;
    }

    /**
     * @param array<mixed, mixed> $part
     * @return array<string, string>
     */
    private function headers(array $part): array
    {
        if (! array_key_exists('headers', $part) || $part['headers'] === null) {
            return [];
        }

        $headers = $part['headers'];
        if (! is_array($headers)) {
            throw new InvalidConfigurationException('Multipart part headers must be an array of strings.');
        }

        $stringHeaders = [];
        foreach ($headers as $headerName => $headerValue) {
            if (! is_string($headerName) || ! is_string($headerValue)) {
                throw new InvalidConfigurationException('Multipart part headers must be string to string.');
            }

            $stringHeaders[$headerName] = $headerValue;
        }

        return $stringHeaders;
    }

    private function prefix(MultipartPart $part): string
    {
        $filename = $this->filename($part);
        $disposition = 'Content-Disposition: form-data; name="' . $part->name . '"';
        if ($filename !== null) {
            $disposition .= '; filename="' . $filename . '"';
        }

        $lines = ['--' . $this->boundary, $disposition];
        $contentType = $this->contentType($part, $filename);
        if ($contentType !== null) {
            $lines[] = 'Content-Type: ' . $contentType;
        }

        foreach ($part->headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines) . "\r\n\r\n";
    }

    private function filename(MultipartPart $part): ?string
    {
        if ($part->filename !== null) {
            return $part->filename;
        }

        return is_string($part->contents) ? null : self::DEFAULT_FILENAME;
    }

    private function contentType(MultipartPart $part, ?string $filename): ?string
    {
        if ($part->contentType !== null) {
            return $part->contentType;
        }

        return $filename === null ? null : self::DEFAULT_FILE_TYPE;
    }

    /**
     * @param list<string|resource|StreamInterface> $chunks
     */
    private function chunksAreSeekable(array $chunks): bool
    {
        foreach ($chunks as $chunk) {
            if (! $this->chunkIsSeekable($chunk)) {
                return false;
            }
        }

        return true;
    }

    private function chunkIsSeekable(mixed $chunk): bool
    {
        if (is_string($chunk)) {
            return true;
        }

        if ($chunk instanceof StreamInterface) {
            return $chunk->isSeekable();
        }

        return is_resource($chunk) && stream_get_meta_data($chunk)['seekable'] === true;
    }

    /**
     * @param list<string|resource|StreamInterface> $chunks
     */
    private function totalSize(array $chunks): ?int
    {
        $size = 0;
        foreach ($chunks as $chunk) {
            $chunkSize = $this->chunkSize($chunk);
            if ($chunkSize === null) {
                return null;
            }

            $size += $chunkSize;
        }

        return $size;
    }

    private function chunkSize(mixed $chunk): ?int
    {
        if (is_string($chunk)) {
            return strlen($chunk);
        }

        if ($chunk instanceof StreamInterface) {
            return $chunk->getSize();
        }

        if (! is_resource($chunk)) {
            return null;
        }

        $stat = fstat($chunk);

        return is_array($stat) ? $stat['size'] : null;
    }
}
