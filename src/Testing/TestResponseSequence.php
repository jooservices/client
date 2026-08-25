<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class TestResponseSequence
{
    /** @var list<ResponseInterface|Throwable> */
    private array $responses = [];

    public function push(ResponseInterface|Throwable $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function next(): ResponseInterface|Throwable
    {
        $next = array_shift($this->responses);
        if ($next === null) {
            throw new RuntimeException('The fake response sequence is empty.');
        }

        return $next;
    }

    public function isEmpty(): bool
    {
        return $this->responses === [];
    }
}
