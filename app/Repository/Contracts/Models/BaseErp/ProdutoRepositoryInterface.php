<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\BaseErp;

use App\Repository\Contracts\RepositoryInterface;

interface ProdutoRepositoryInterface extends RepositoryInterface
{
    public function findCodigoBarraById(int $idProduto): ?string;
}
