<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class VendaInformacoes extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.venda_informacoes';

    protected $fillable = [
        'id_venda',
        'valor_frete',
        'volumes',
        'id_cadastro',
        'info_adicional',
    ];
}
