<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Catalog;

use App\Models\Food99\Catalog\Food99ShopItem;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;

/**
 * Repositorio Eloquent de itens de loja da 99Food.
 */
class Food99ShopItemEloquentRepository extends EloquentRepository implements Food99ShopItemRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * @param Food99ShopItem $model Model de item da 99Food
     */
    public function __construct(Food99ShopItem $model)
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
            ->orderBy('id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByShopIdAndAppItemId(int $food99ShopId, string $appItemId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->where('app_item_id', $appItemId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByShopIdAndAppItemIds(int $food99ShopId, array $appItemIds): ?object
    {
        if ($appItemIds === []) {
            return $this->model->newCollection();
        }

        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->whereIn('app_item_id', $appItemIds)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByShopIdAndProdutoGrade(int $food99ShopId, int $idProduto, ?int $idGrade): ?object
    {
        $query = $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->where('id_produto', $idProduto);

        if ($idGrade === null) {
            $query->whereNull('id_grade');
        } else {
            $query->where('id_grade', $idGrade);
        }

        return $query->first();
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
            ->orderBy('id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function markPublishedByShopAndAppItemIds(
        int $food99ShopId,
        array $appItemIds,
        array $itemPayloadByAppItemId = [],
    ): void {
        if ($appItemIds === []) {
            return;
        }

        $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->whereIn('app_item_id', $appItemIds)
            ->update([
                'publish_status' => 'published',
                'last_published_at' => now(),
                'last_error_message' => null,
            ]);

        foreach ($appItemIds as $appItemId) {
            $payloadSnapshot = $itemPayloadByAppItemId[$appItemId] ?? null;
            if (! is_array($payloadSnapshot)) {
                continue;
            }

            $this->model
                ->newQuery()
                ->where('food99_shop_id', $food99ShopId)
                ->where('app_item_id', $appItemId)
                ->update([
                    'payload_snapshot' => $payloadSnapshot,
                ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function markFailedByShopAndAppItemIds(int $food99ShopId, array $appItemIds, string $errorMessage): void
    {
        if ($appItemIds === []) {
            return;
        }

        $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->whereIn('app_item_id', $appItemIds)
            ->where('publish_status', '!=', 'published')
            ->update([
                'publish_status' => 'failed',
                'last_error_message' => mb_substr($errorMessage, 0, 500),
            ]);
    }

    public function updateCategoryIdByIds(int $categoryId, array $itemIds): int
    {
        if ($itemIds === []) {
            return 0;
        }

        return $this->model
            ->newQuery()
            ->whereIn('id', $itemIds)
            ->update([
                'food99_shop_category_id' => $categoryId,
            ]);
    }
}
