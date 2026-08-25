<?php

declare(strict_types=1);

namespace JOOservices\Client\Validation;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\ResponseValidationException;
use JOOservices\Client\Support\StreamContents;
use JsonException;
use JsonSchema\Validator;
use Psr\Http\Message\ResponseInterface;

final class JsonSchemaBodyValidator
{
    /** @param array<string, mixed> $schema */
    public function __construct(private readonly array $schema, private readonly StreamContents $streams = new StreamContents())
    {
    }

    public function validate(ResponseInterface $response): bool
    {
        if (! class_exists(Validator::class)) {
            throw new InvalidConfigurationException('JSON Schema validation requires justinrainbow/json-schema.');
        }

        try {
            $data = json_decode($this->streams->read($response->getBody()), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ResponseValidationException('Response body is not valid JSON for schema validation.', 0, $error);
        }

        $validator = new Validator();
        $validator->validate($data, (object) $this->schema);

        return $validator->isValid() === true;
    }
}
