<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport\Curl;

final class CurlHeaderBuffer
{
    /** @var array<string, list<string>> */
    public array $headers = [];

    public int $status = 0;

    private ?string $lastHeaderName = null;

    public function append(mixed $handle, string $line): int
    {
        unset($handle);
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $matches) === 1) {
            $this->status = (int) $matches[1];
            $this->headers = [];
            $this->lastHeaderName = null;

            return strlen($line);
        }

        if (rtrim($line, "\r\n") === '') {
            $this->lastHeaderName = null;

            return strlen($line);
        }

        // RFC 7230 obs-fold: a continuation line starts with a space or tab and extends the PREVIOUS
        // header's value. Without this, the old behavior parsed the first colon inside the continuation
        // text as if it were a brand new "name: value" header line.
        if (($line[0] === ' ' || $line[0] === "\t") && $this->lastHeaderName !== null && isset($this->headers[$this->lastHeaderName])) {
            $values = $this->headers[$this->lastHeaderName];
            $last = array_pop($values);
            if ($last !== null) {
                $values[] = $last . ' ' . trim($line);
                $this->headers[$this->lastHeaderName] = $values;
            }

            return strlen($line);
        }

        $position = strpos($line, ':');
        if ($position !== false) {
            $name = trim(substr($line, 0, $position));
            $this->headers[$name][] = trim(substr($line, $position + 1));
            $this->lastHeaderName = $name;
        }

        return strlen($line);
    }
}
