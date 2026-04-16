<?php

declare(strict_types=1);

namespace App\Models\Food99\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent de token por loja da 99Food.
 *
 * Usa conexao mysql_marketplace por representar dados externos de integracao.
 * IDs da 99Food sao mantidos como string para evitar problemas de int64.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class Food99StoreToken extends Model
{
    /**
     * Nome da conexao de banco usada por este model.
     */
    protected $connection = 'mysql_marketplace';

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'food99_shop_tokens';

    /**
     * Campos permitidos para mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'food99_shop_id',
        'app_shop_id',
        'auth_token',
        'expires_at',
        'last_refreshed_at',
        'last_retrieved_at',
        'payload',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_retrieved_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
