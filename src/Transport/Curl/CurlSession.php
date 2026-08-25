<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport\Curl;

use CurlHandle;
use CurlShareHandle;
use JOOservices\Client\Exceptions\InvalidConfigurationException;

final class CurlSession
{
    private ?CurlHandle $handle = null;

    private ?CurlShareHandle $share = null;

    private ?int $pid = null;

    public function handle(): CurlHandle
    {
        $this->guardAgainstFork();
        $this->share();
        if ($this->handle instanceof CurlHandle) {
            curl_reset($this->handle);

            return $this->handle;
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new InvalidConfigurationException('Unable to initialize cURL.');
        }

        $this->handle = $handle;

        return $handle;
    }

    public function share(): CurlShareHandle
    {
        $this->guardAgainstFork();
        if ($this->share instanceof CurlShareHandle) {
            return $this->share;
        }

        $share = curl_share_init();

        curl_share_setopt($share, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
        curl_share_setopt($share, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
        if (defined('CURL_LOCK_DATA_CONNECT')) {
            curl_share_setopt($share, CURLSHOPT_SHARE, CURL_LOCK_DATA_CONNECT);
        }

        $this->share = $share;

        return $this->share;
    }

    public function close(): void
    {
        $this->handle = null;
        $this->share = null;
        $this->pid = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * libcurl handles must never be reused across a fork() boundary — each process needs its own
     * connection/DNS cache, and sharing one is documented libcurl undefined behavior. A queue worker
     * that calls pcntl_fork() after this session already opened handles would otherwise corrupt shared
     * state between parent and child. Detect the fork by PID change and start fresh in that process
     * instead, rather than requiring every caller to remember to call close() themselves post-fork.
     */
    private function guardAgainstFork(): void
    {
        $pid = getmypid();
        if ($pid === false) {
            return;
        }

        if ($this->pid === null) {
            $this->pid = $pid;

            return;
        }

        if ($pid !== $this->pid) {
            $this->handle = null;
            $this->share = null;
            $this->pid = $pid;
        }
    }
}
