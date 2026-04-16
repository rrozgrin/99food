<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\BaseErp;

use App\Repository\Contracts\RepositoryInterface;

interface GradeRepositoryInterface extends RepositoryInterface
{
    public function findLatestByProdutoId(int $idProduto): ?object;
}
