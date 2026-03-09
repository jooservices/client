<?php

declare(strict_types=1);

namespace JOOservices\Client\Response;

use InvalidArgumentException;
use JOOservices\Client\Contracts\ResponseWrapperInterface;
use JOOservices\Client\Exceptions\JsonDecodingException;
use JsonException;
use Psr\Http\Message\ResponseInterface;

final class ResponseWrapper implements ResponseWrapperInterface
{
    public function __construct(
        private readonly ResponseInterface $response
    ) {
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function header(string $name): ?string
    {
        return $this->response->getHeaderLine($name) ?: null;
    }

    public function json(): array
    {
        $body = (string) $this->response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                throw new JsonDecodingException('JSON decoded body is not an array.');
            }

            return $decoded;
        } catch (JsonException $e) {
            throw new JsonDecodingException('Failed to decode JSON response: ' . $e->getMessage(), 0, $e);
        }
    }

    public function toPsrResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function toDto(string $dtoClass): object
    {
        if (! class_exists($dtoClass)) {
            throw new InvalidArgumentException("DTO class $dtoClass does not exist");
        }

        // Assuming jooservices/dto Dto::from() or similar static factory exists.
        // We use a dynamic call to forward the array.
        if (! method_exists($dtoClass, 'from')) {
            throw new InvalidArgumentException("DTO class $dtoClass must have a static from() method");
        }

        return $dtoClass::from($this->json());
    }
}
