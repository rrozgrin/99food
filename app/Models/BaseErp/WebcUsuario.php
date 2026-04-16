<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class WebcUsuario extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.webc_usuario';

    protected $fillable = [
        'id_cadastro',
        'nome',
        'ativo',
    ];
}
