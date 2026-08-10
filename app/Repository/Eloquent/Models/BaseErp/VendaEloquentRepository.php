<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\Venda;
use App\Repository\Contracts\Models\BaseErp\VendaRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

class VendaEloquentRepository extends EloquentRepository implements VendaRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(Venda $model)
    {
        parent::__construct($model);
    }

    public function findIdByCadastroOrigemDescricao(int $idCadastro, string $origemVenda, string $descricaoVenda): ?int
    {
        $id = $this->model
            ->newQuery()
            ->where('id_cadastro', $idCadastro)
            ->where('origem_venda', $origemVenda)
            ->where('descricao_venda', $descricaoVenda)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function updateSituacaoById(int $idVenda, string $situacao): void
    {
        $this->model
            ->newQuery()
            ->where('id', $idVenda)
            ->update([
                'situacao' => $situacao,
            ]);
    }
}
