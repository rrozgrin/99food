<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model de permissão de acesso do ERP.
 *
 * Permissões seguem o padrão: {modulo}.{acao}
 * Exemplos: 'clientes.ver', 'clientes.editar', 'vendas.cancelar',
 *           'caixa.fechar', 'relatorio.financeiro', 'admin.tudo'
 *
 * Tabela: webc_permissoes
 */
class Permission extends Model
{
    protected $connection = 'mysql';

    protected $table = 'webc_permissoes';

    protected $fillable = [
        'nome',
        'descricao',
        'modulo',
    ];

    public $timestamps = false;

    /**
     * Roles que possuem esta permissão.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Role::class,
            table: 'webc_role_permissao',
            foreignPivotKey: 'permissao_id',
            relatedPivotKey: 'role_id',
        );
    }
}
