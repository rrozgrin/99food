<?php

declare(strict_types=1);

namespace App\Models\Food99\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent da credencial configurada para integracao 99Food.
 */
class Food99AppCredential extends Model
{
    /**
     * Nome da conexao de banco usada por este model.
     */
    protected $connection = 'mysql_marketplace';

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'food99_app_credentials';

    /**
     * Campos permitidos para mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'environment',
        'app_id',
        'app_secret',
        'base_url',
        'status',
    ];
}
