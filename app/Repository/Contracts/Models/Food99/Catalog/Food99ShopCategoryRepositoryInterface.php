<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Catalog;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia para categorias de loja da 99Food.
 */
interface Food99ShopCategoryRepositoryInterface extends RepositoryInterface
{
    /**
     * Retorna categorias da loja.
     *
     * @param int $food99ShopId ID interno da loja em mysql_marketplace
     *
     * @return object|null Colecao de categorias
     */
    public function findByShopId(int $food99ShopId): ?object;

    /**
     * Busca categoria por loja e app_category_id.
     *
     * @param int    $food99ShopId  ID interno da loja
     * @param string $appCategoryId ID externo da categoria
     *
     * @return object|null Categoria encontrada
     */
    public function findByShopIdAndAppCategoryId(int $food99ShopId, string $appCategoryId): ?object;

    public function findIdByShopAndAppCategoryId(int $food99ShopId, string $appCategoryId): ?int;

    /**
     * Retorna categorias ativas por loja.
     *
     * @param int $food99ShopId ID interno da loja em mysql_marketplace
     *
     * @return object|null Colecao de categorias
     */
    public function findActiveByShopId(int $food99ShopId): ?object;
}
