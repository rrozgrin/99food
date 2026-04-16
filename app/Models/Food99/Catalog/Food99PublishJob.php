<?php

declare(strict_types=1);

namespace App\Models\Food99\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent de log operacional de publicacao 99Food.
 */
class Food99PublishJob extends Model
{
    /**
     * Nome da conexao de banco usada por este model.
     */
    protected $connection = 'mysql_marketplace';

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'food99_publish_jobs';

    /**
     * Campos permitidos para mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'food99_shop_id',
        'event_type',
        'status',
        'request_id',
        'task_id',
        'triggered_by_user_id',
        'started_at',
        'finished_at',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triggered_by_user_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }
}

