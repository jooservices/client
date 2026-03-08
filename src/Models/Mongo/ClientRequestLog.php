<?php

declare(strict_types=1);

namespace JOOservices\Client\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

final class ClientRequestLog extends Model
{
    /** @var string */
    protected $connection = 'mongodb';

    /** @var string */
    protected $collection = 'client_request_logs';

    /**
     * Explicit allow-list for mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'level',
        'message',
        'method',
        'uri',
        'status',
        'duration_ms',
        'correlation_id',
        'request_payload',
        'response_payload',
        'payload_truncated',
        'context',
        'exception',
        'logged_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'integer',
        'duration_ms' => 'float',
        'payload_truncated' => 'boolean',
        'context' => 'array',
        'logged_at' => 'datetime',
    ];
}
