<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\Grade;
use App\Repository\Contracts\Models\BaseErp\GradeRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

class GradeEloquentRepository extends EloquentRepository implements GradeRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(Grade $model)
    {
        parent::__construct($model);
    }

    public function findLatestByProdutoId(int $idProduto): ?object
    {
        return $this->model
            ->newQuery()
            ->where('id_produto', $idProduto)
            ->orderByDesc('id_grade')
            ->first();
    }
}
