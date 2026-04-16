<?php

declare(strict_types=1);

namespace App\Models\Food99\Webhook;

use Illuminate\Database\Eloquent\Model;

class Food99WebhookInboundLog extends Model
{
    protected $connection = 'mysql_marketplace';

    protected $table = 'food99_webhook_inbound_logs';

    protected $fillable = [
        'food99_app_credential_id',
        'food99_shop_id',
        'event_name',
        'request_id',
        'status',
        'headers',
        'payload',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'food99_app_credential_id' => 'integer',
            'food99_shop_id' => 'integer',
            'headers' => 'array',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

