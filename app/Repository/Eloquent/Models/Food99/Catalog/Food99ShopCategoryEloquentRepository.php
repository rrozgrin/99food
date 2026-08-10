<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Catalog;

use App\Models\Food99\Catalog\Food99ShopCategory;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

/**
 * Repositorio Eloquent de categorias de loja da 99Food.
 */
class Food99ShopCategoryEloquentRepository extends EloquentRepository implements Food99ShopCategoryRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * @param  Food99ShopCategory  $model  Model de categoria da 99Food
     */
    public function __construct(Food99ShopCategory $model)
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
    public function findByShopIdAndAppCategoryId(int $food99ShopId, string $appCategoryId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->where('app_category_id', $appCategoryId)
            ->first();
    }

    public function findIdByShopAndAppCategoryId(int $food99ShopId, string $appCategoryId): ?int
    {
        $id = $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->where('app_category_id', $appCategoryId)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
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
