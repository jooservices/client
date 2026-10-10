<?php

declare(strict_types=1);

namespace JOOservices\Client\Logging;

final class LogSanitizer
{
    /**
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    public function sanitize(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSecret($key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $value = preg_replace('/(:\/\/)[^\/\s@]+:[^\/\s@]*@/', '$1[redacted]@', $value) ?? $value;
                $context[$key] = $this->sanitizeQueryValues($value);
            }
        }

        return $context;
    }

    private function sanitizeQueryValues(string $value): string
    {
        $length = strlen($value);
        $result = '';
        $copyFrom = 0;

        for ($offset = 0; $offset < $length; ++$offset) {
            if (!str_contains('?#&;', $value[$offset])) {
                continue;
            }

            $parameter = $this->parseQueryParameterAt($value, $offset + 1);
            if ($parameter === null) {
                continue;
            }

            $valueEnd = $this->findQueryValueEnd($value, $parameter['valueStart']);
            if ($this->isSensitiveQueryKey(rawurldecode($parameter['key']))) {
                $result .= substr($value, $copyFrom, $parameter['valueStart'] - $copyFrom) . '[redacted]';
                $copyFrom = $valueEnd;
            }

            $offset = $valueEnd - 1;
        }

        return $copyFrom === 0 ? $value : $result . substr($value, $copyFrom);
    }

    /**
     * @return array{key: string, valueStart: int}|null
     */
    private function parseQueryParameterAt(string $value, int $start): ?array
    {
        $length = strlen($value);

        for ($offset = $start; $offset < $length; ++$offset) {
            if ($value[$offset] === '=') {
                if ($offset === $start) {
                    return null;
                }

                return [
                    'key' => substr($value, $start, $offset - $start),
                    'valueStart' => $offset + 1,
                ];
            }

            if (str_contains('?#&;', $value[$offset])) {
                return null;
            }
        }

        return null;
    }

    private function findQueryValueEnd(string $value, int $start): int
    {
        $length = strlen($value);

        for ($offset = $start; $offset < $length; ++$offset) {
            if ($value[$offset] === '&' || $value[$offset] === '#') {
                return $offset;
            }

            if ($value[$offset] === ';' && $this->parseQueryParameterAt($value, $offset + 1) !== null) {
                return $offset;
            }
        }

        return $length;
    }

    private function isSensitiveQueryKey(string $key): bool
    {
        return preg_match('/^[\w-]*(?:token|api[_-]?key|password|secret|sig|signature|auth|jwt|session|sid|credential)[\w-]*$/i', $key) === 1;
    }

    private function isSecret(string $key): bool
    {
        // A signature (e.g. HmacSha256Signer's X-Signature) is replayable if leaked and the signed
        // payload has no timestamp/nonce, so it belongs in the same bucket as the other secrets here —
        // this list must stay in sync with the query-string pattern in sanitize() above and with
        // RedirectHandler's needles (token, secret, password, credential).
        return preg_match('/authorization|cookie|token|api[_-]?key|password|secret|sig(nature)?|jwt|session|sid|credential/i', $key) === 1;
    }
}
