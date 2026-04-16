<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Catalog;

use App\Models\Food99\Catalog\Food99ShopMenu;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopMenuRepositoryInterface;

/**
 * Repositorio Eloquent de menus de loja da 99Food.
 */
class Food99ShopMenuEloquentRepository extends EloquentRepository implements Food99ShopMenuRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * @param Food99ShopMenu $model Model de menu da 99Food
     */
    public function __construct(Food99ShopMenu $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByShopId(int $food99ShopId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByShopIdAndAppMenuId(int $food99ShopId, string $appMenuId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->where('app_menu_id', $appMenuId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveByShopId(int $food99ShopId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
