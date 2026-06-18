<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Catalog;

use Illuminate\Support\Facades\DB;
use App\Models\Food99\Catalog\Food99ShopItem;
use App\Models\Food99\Catalog\Food99ShopCategoryItem;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryItemRepositoryInterface;

/**
 * Repositorio Eloquent da pivot categoria-item da 99Food.
 */
class Food99ShopCategoryItemEloquentRepository extends EloquentRepository implements Food99ShopCategoryItemRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * @param Food99ShopCategoryItem $model Model da pivot categoria-item
     */
    public function __construct(Food99ShopCategoryItem $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByCategoryIdAndItemId(int $categoryId, int $itemId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_category_id', $categoryId)
            ->where('food99_shop_item_id', $itemId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByCategoryIds(array $categoryIds): ?object
    {
        if ($categoryIds === []) {
            return $this->model->newCollection();
        }

        return $this->model
            ->newQuery()
            ->whereIn('food99_shop_category_id', $categoryIds)
            ->orderBy('food99_shop_category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByCategoryId(int $categoryId): int
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_category_id', $categoryId)
            ->delete();
    }

    public function deleteByItemId(int $itemId): int
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_item_id', $itemId)
            ->delete();
    }

    public function replaceLinksByCategory(int $categoryId, array $shopItemIds): void
    {
        DB::connection('mysql_marketplace')->transaction(function () use ($categoryId, $shopItemIds): void {
            $this->deleteByCategoryId($categoryId);

            foreach ($shopItemIds as $index => $shopItemId) {
                $this->model
                    ->newQuery()
                    ->create([
                        'food99_shop_category_id' => $categoryId,
                        'food99_shop_item_id' => (int) $shopItemId,
                        'sort_order' => $index + 1,
                    ]);
            }

            if ($shopItemIds !== []) {
                Food99ShopItem::query()
                    ->whereIn('id', $shopItemIds)
                    ->update([
                        'food99_shop_category_id' => $categoryId,
                    ]);
            }
        });
    }
}
