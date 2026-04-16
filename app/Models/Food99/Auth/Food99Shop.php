<?php

declare(strict_types=1);

namespace App\Models\Food99\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent de loja vinculada no hub para a 99Food.
 */
class Food99Shop extends Model
{
    /**
     * Nome da conexao de banco usada por este model.
     */
    protected $connection = 'mysql_marketplace';

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'food99_shops';

    /**
     * Campos permitidos para mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'food99_app_credential_id',
        'id_cadastro',
        'app_shop_id',
        'food99_shop_id',
        'name',
        'binding_status',
        'auth_status',
        'last_synced_at',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'food99_app_credential_id' => 'integer',
            'id_cadastro' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }
}
