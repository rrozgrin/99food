<?php

declare(strict_types=1);

namespace App\Models\BaseErp;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'base_erp.grade';

    protected $primaryKey = 'id_grade';

    protected $fillable = [
        'id_grade',
        'id_cadastro',
        'id_produto',
        'codigo_barra',
        'codigo_interno',
        'valor_custo',
        'valor_varejo_aprazo',
        'ativo',
    ];
}
