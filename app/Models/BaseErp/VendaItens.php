<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class VendaItens extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.venda_itens';

    protected $fillable = [
        'qtd',
        'id_venda',
        'id_produto',
        'id_grade',
        'nome_produto',
        'preco_tabela',
        'preco_venda',
        'codigo_barra',
        'id_cadastro',
    ];
}

