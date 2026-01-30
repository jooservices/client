<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use Psr\Http\Message\ResponseInterface;

interface ResponseWrapperInterface
{
    /**
     * Get the status code.
     */
    public function status(): int;

    /**
     * Get a specific header line.
     */
    public function header(string $name): ?string;

    /**
     * Get the JSON decoded body.
     *
     * @return array<mixed>
     *
     * @throws \JsonException|\RuntimeException
     */
    public function json(): array;

    /**
     * Get the underlying PSR-7 Response.
     */
    public function toPsrResponse(): ResponseInterface;

    /**
     * Hydrate the response into a DTO.
     *
     * @template T of object
     *
     * @param  class-string<T>  $dtoClass
     * @return T
     */
    public function toDto(string $dtoClass): object;
}
