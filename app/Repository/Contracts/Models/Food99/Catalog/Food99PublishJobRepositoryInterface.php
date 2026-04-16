<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Catalog;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia para logs de publicacao 99Food.
 */
interface Food99PublishJobRepositoryInterface extends RepositoryInterface
{
    /**
     * Lista jobs recentes por loja.
     *
     * @param int $food99ShopId ID interno da loja
     * @param int $limit        Quantidade maxima de registros
     *
     * @return object Colecao de jobs
     */
    public function findLatestByShopId(int $food99ShopId, int $limit = 20): object;
}
