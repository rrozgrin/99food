<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\Produto;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\BaseErp\ProdutoRepositoryInterface;

class ProdutoEloquentRepository extends EloquentRepository implements ProdutoRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(Produto $model)
    {
        parent::__construct($model);
    }

    public function findCodigoBarraById(int $idProduto): ?string
    {
        $codigo = $this->model
            ->newQuery()
            ->where('id', $idProduto)
            ->value('codigo_barra');

        return is_string($codigo) ? $codigo : null;
    }
}
