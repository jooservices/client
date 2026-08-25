<?php

declare(strict_types=1);

namespace JOOservices\Client\Response;

use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Exceptions\HttpResponseException;
use JOOservices\Client\Exceptions\JsonDecodingException;
use JOOservices\Client\Support\StreamContents;
use JOOservices\Dto\Core\Dto;
use JsonException;
use Psr\Http\Message\ResponseInterface;

final class Response
{
    /** Safety ceiling on the materialized body; body()/json()/object()/toDto()/collect() all route through it. */
    private const MAX_BODY_BYTES = 104_857_600;

    /** @var array<mixed>|null */
    private ?array $json = null;

    private bool $jsonDecoded = false;

    private ?string $cachedBody = null;

    private ?object $object = null;

    private function __construct(
        private readonly ResponseInterface $psr,
        private readonly StreamContents $streams = new StreamContents(),
    ) {
    }

    public static function from(ResponseInterface $psr): self
    {
        return new self($psr);
    }

    public function status(): int
    {
        return $this->psr->getStatusCode();
    }

    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    public function header(string $name): ?string
    {
        $value = $this->psr->getHeaderLine($name);

        return $value === '' ? null : $value;
    }

    /** @return array<array-key, list<string>> */
    public function headers(): array
    {
        return array_map(static fn(array $values): array => array_values($values), $this->psr->getHeaders());
    }

    public function body(): string
    {
        if ($this->cachedBody !== null) {
            return $this->cachedBody;
        }

        $body = $this->streams->read($this->psr->getBody());
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new DownloadSizeExceededException(sprintf('Response body exceeds the %d byte limit.', self::MAX_BODY_BYTES));
        }

        return $this->cachedBody = $body;
    }

    /** @return array<mixed> */
    public function json(): array
    {
        if ($this->jsonDecoded) {
            return $this->json ?? [];
        }

        try {
            $decoded = json_decode($this->withoutBom($this->body()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new JsonDecodingException('Response body is not valid JSON.', 0, $error);
        }

        if (! is_array($decoded)) {
            throw new JsonDecodingException('Expected a JSON object or array response.');
        }

        $this->json = $decoded;
        $this->jsonDecoded = true;

        return $decoded;
    }

    public function object(): object
    {
        if ($this->object !== null) {
            return $this->object;
        }

        try {
            $decoded = json_decode($this->withoutBom($this->body()), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new JsonDecodingException('Response body is not valid JSON.', 0, $error);
        }

        if (! is_object($decoded)) {
            throw new JsonDecodingException('Expected a JSON object response.');
        }

        return $this->object = $decoded;
    }

    /** @param class-string $dtoClass */
    public function toDto(string $dtoClass): Dto
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->json();

        return $this->hydrate($dtoClass, $payload);
    }

    /**
     * @param class-string $dtoClass
     * @return list<Dto>
     */
    public function collect(string $dtoClass): array
    {
        $items = [];
        foreach ($this->json() as $row) {
            if (! is_array($row)) {
                throw new JsonDecodingException('Expected each collected row to be a JSON object or array.');
            }

            $items[] = $this->hydrate($dtoClass, $row);
        }

        return $items;
    }

    public function throw(): self
    {
        if ($this->status() < 200 || $this->status() >= 300) {
            throw new HttpResponseException($this->psr);
        }

        return $this;
    }

    public function toPsrResponse(): ResponseInterface
    {
        return $this->psr;
    }

    /**
     * @param class-string $dtoClass
     * @param array<mixed> $data
     */
    private function hydrate(string $dtoClass, array $data): Dto
    {
        if (! is_a($dtoClass, Dto::class, true)) {
            throw new JsonDecodingException('toDto() requires a JOOservices DTO class.');
        }

        /** @var class-string<Dto> $dtoClass */
        /** @var array<string, mixed> $payload */
        $payload = $data;
        $factory = [$dtoClass, 'from'];

        return $factory($payload);
    }

    private function withoutBom(string $body): string
    {
        return str_starts_with($body, "\xEF\xBB\xBF") ? substr($body, 3) : $body;
    }
}
