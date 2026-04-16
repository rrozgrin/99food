<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class Venda extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.venda';

    protected $fillable = [
        'id_tipo_venda',
        'id_cadastro',
        'id_usuario',
        'id_cliente',
        'data_venda',
        'hora_venda',
        'situacao',
        'tipo_pgto',
        'origem_venda',
        'pago',
        'id_placa',
        'descricao_venda',
        'observacao',
    ];
}
