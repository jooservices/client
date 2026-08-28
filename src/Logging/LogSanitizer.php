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
                $context[$key] = preg_replace('/([?&][\w-]*(?:token|api[_-]?key|password|secret|sig|signature|auth|jwt|session|sid|credential)[\w-]*=)[^&]*/i', '$1[redacted]', $value) ?? $value;
            }
        }

        return $context;
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
