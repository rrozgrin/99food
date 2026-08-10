<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class VendaPagamento extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.venda_pagamento';

    protected $fillable = [
        'id_venda',
        'id_forma_pgto',
        'valor_pgto',
        'qtd_parcela',
        'id_cadastro',
    ];
}
