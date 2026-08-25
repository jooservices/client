<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\RequestException;
use Psr\Http\Message\RequestInterface;

final class HeaderValidator
{
    public function assertName(string $name): void
    {
        if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name) !== 1) {
            throw new InvalidConfigurationException('Invalid HTTP header name.');
        }
    }

    public function assertValue(string $value): void
    {
        // CR/LF is the security-critical case (header/request-line injection), but any other control
        // character is also invalid per RFC 7230's field-value grammar, and different transports handle
        // one late and inconsistently: libcurl rejects some, a PSR-18 client may pass it straight
        // through. Reject the whole class here instead of just the two bytes that happen to be exploitable.
        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
            throw new InvalidConfigurationException('Invalid HTTP header value.');
        }
    }

    public function assertPair(string $name, string $value): void
    {
        $this->assertName($name);
        $this->assertValue($value);
    }

    public function assertRequest(RequestInterface $request): void
    {
        try {
            foreach ($request->getHeaders() as $name => $values) {
                $this->assertName((string) $name);
                foreach ($values as $value) {
                    $this->assertValue($value);
                }
            }
        } catch (InvalidConfigurationException $error) {
            throw new RequestException($request, $error->getMessage(), $error);
        }
    }
}
