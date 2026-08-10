<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Catalog;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia para menus de loja da 99Food.
 */
interface Food99ShopMenuRepositoryInterface extends RepositoryInterface
{
    /**
     * Retorna menus da loja.
     *
     * @param  int  $food99ShopId  ID interno da loja em mysql_marketplace
     * @return object|null Colecao de menus
     */
    public function findByShopId(int $food99ShopId): ?object;

    /**
     * Busca um menu por loja e app_menu_id.
     *
     * @param  int  $food99ShopId  ID interno da loja
     * @param  string  $appMenuId  ID externo do menu
     * @return object|null Menu encontrado
     */
    public function findByShopIdAndAppMenuId(int $food99ShopId, string $appMenuId): ?object;

    /**
     * Retorna menus ativos por loja.
     *
     * @param  int  $food99ShopId  ID interno da loja em mysql_marketplace
     * @return object|null Colecao de menus
     */
    public function findActiveByShopId(int $food99ShopId): ?object;
}
