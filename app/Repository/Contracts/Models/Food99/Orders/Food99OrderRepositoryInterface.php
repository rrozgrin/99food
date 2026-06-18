<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Orders;

use App\Repository\Contracts\RepositoryInterface;

interface Food99OrderRepositoryInterface extends RepositoryInterface
{
    public function findByShopIdAndOrderId(int $food99ShopId, string $orderId): ?object;

    public function findByIdForUpdate(int $food99OrderId): ?object;

    public function markAsProcessing(int $food99OrderId): ?object;

    public function updateById(int $food99OrderId, array $data): bool;

    public function listByShopIds(array $shopIds, int $limit): ?object;

    public function upsertOrderWithItems(
        array $orderAttributes,
        array $orderValues,
        array $items,
    ): int;
}
