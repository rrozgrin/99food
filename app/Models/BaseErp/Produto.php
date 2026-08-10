<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.produto';

    protected $fillable = [
        'descricao',
        'id_cadastro',
        'id_usuario',
        'data_cadastro',
        'ativo',
        'codigo_barra',
        'barra',
        'ean',
        'identificacao_interna',
        'custo',
        'custo_medio_venda',
        'custo_medio_venda_atacado',
        'qtd_minima',
        'locacao_quantidade',
    ];
}
