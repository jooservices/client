<?php

declare(strict_types=1);

namespace JOOservices\Client\Request;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Support\HeaderValidator;
use Psr\Http\Message\StreamInterface;

final readonly class MultipartPart
{
    /**
     * @param string|resource|StreamInterface $contents
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $name,
        public mixed $contents,
        public ?string $filename = null,
        public ?string $contentType = null,
        public array $headers = [],
        HeaderValidator $headerValidator = new HeaderValidator(),
    ) {
        $this->assertName($this->name);
        $this->assertContents($this->contents, $this->filename);
        if ($this->filename !== null) {
            $this->assertSafeAttribute($this->filename, 'filename');
        }

        if ($this->contentType !== null) {
            $headerValidator->assertValue($this->contentType);
        }

        foreach ($this->headers as $headerName => $headerValue) {
            $headerValidator->assertPair($headerName, $headerValue);
        }
    }

    private function assertName(string $name): void
    {
        if ($name === '') {
            throw new InvalidConfigurationException('Multipart field names cannot be empty.');
        }

        $this->assertSafeAttribute($name, 'name');
    }

    private function assertSafeAttribute(string $value, string $label): void
    {
        if (preg_match('/[\x00-\x1F\x7F"\\\\]/', $value) === 1) {
            throw new InvalidConfigurationException(sprintf('Invalid multipart %s.', $label));
        }
    }

    private function assertContents(mixed $contents, ?string $filename): void
    {
        if (! is_string($contents) && ! is_resource($contents) && ! $contents instanceof StreamInterface) {
            throw new InvalidConfigurationException('Multipart contents must be a string, stream resource, or PSR-7 stream.');
        }

        if (is_string($contents) && $filename === null && preg_match('/[\r\n]/', $contents) === 1) {
            throw new InvalidConfigurationException('Multipart text fields cannot contain CR or LF.');
        }
    }
}
