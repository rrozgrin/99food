<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.cliente';

    protected $fillable = [
        'id_cadastro',
        'tipo_pessoa',
        'nome',
        'email',
        'telefone',
        'celular',
        'ativo',
    ];
}

