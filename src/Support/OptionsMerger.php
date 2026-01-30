<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

final class OptionsMerger
{
    /**
     * Merge global options with request-specific options.
     * Simple array merge for now, but encapsulated for future logic.
     *
     * @param  array<string, mixed>  $baseOptions
     * @param  array<string, mixed>  $requestOptions
     * @return array<string, mixed>
     */
    public function merge(array $baseOptions, array $requestOptions): array
    {
        // Recursive merge might be needed for headers, but for Guzzle options
        // array_merge replaces top-level keys. Valid for Phase 1.

        if (
            isset($requestOptions['headers']) && is_array($requestOptions['headers']) &&
            isset($baseOptions['headers']) && is_array($baseOptions['headers'])
        ) {
            // Deep merge headers
            $headers = array_merge($baseOptions['headers'], $requestOptions['headers']);
            $merged = array_merge($baseOptions, $requestOptions);
            $merged['headers'] = $headers;

            return $merged;
        }

        return array_merge($baseOptions, $requestOptions);
    }
}
