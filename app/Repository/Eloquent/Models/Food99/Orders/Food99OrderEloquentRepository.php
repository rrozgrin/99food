<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Orders;

use Illuminate\Support\Facades\DB;
use App\Models\Food99\Orders\Food99Order;
use App\Models\Food99\Orders\Food99OrderItem;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderRepositoryInterface;

class Food99OrderEloquentRepository extends EloquentRepository implements Food99OrderRepositoryInterface
{
    protected string $connection = 'mysql_marketplace';

    public function __construct(Food99Order $model)
    {
        parent::__construct($model);
    }

    public function findByShopIdAndOrderId(int $food99ShopId, string $orderId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->where('order_id', $orderId)
            ->first();
    }

    public function findByIdForUpdate(int $food99OrderId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('id', $food99OrderId)
            ->lockForUpdate()
            ->first();
    }

    public function markAsProcessing(int $food99OrderId): ?object
    {
        return DB::connection('mysql_marketplace')->transaction(function () use ($food99OrderId): ?object {
            $order = $this->findByIdForUpdate($food99OrderId);

            if (! is_object($order)) {
                return null;
            }

            if (is_numeric(data_get($order, 'id_venda')) && (int) data_get($order, 'id_venda') > 0) {
                return null;
            }

            // Evita corrida entre jobs duplicadas: se ja entrou em processamento,
            // nao deixa um segundo worker seguir com o mesmo pedido.
            if ((string) data_get($order, 'sync_status') === 'processing_erp') {
                return null;
            }

            $order->sync_status = 'processing_erp';
            $order->error_message = null;
            $order->save();

            return $order;
        });
    }

    public function updateById(int $food99OrderId, array $data): bool
    {
        return (bool) $this->model
            ->newQuery()
            ->where('id', $food99OrderId)
            ->update($data);
    }

    public function listByShopIdsAndStatuses(array $shopIds, array $statuses, int $limit): ?object
    {
        if ($shopIds === []) {
            return $this->model->newCollection();
        }

        return $this->model
            ->newQuery()
            ->whereIn('food99_shop_id', $shopIds)
            ->whereIn('sync_status', $statuses)
            ->with([
                'items' => static function ($query): void {
                    $query->orderBy('id');
                },
            ])
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)))
            ->get();
    }

    public function upsertOrderWithItems(
        array $orderAttributes,
        array $orderValues,
        array $items,
    ): int {
        return (int) DB::connection('mysql_marketplace')->transaction(function () use ($orderAttributes, $orderValues, $items): int {
            $order = $this->model->updateOrCreate($orderAttributes, $orderValues);

            $orderDbId = (int) data_get($order, 'id');
            if ($orderDbId <= 0) {
                return 0;
            }

            Food99OrderItem::query()
                ->where('food99_order_id', $orderDbId)
                ->delete();

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                Food99OrderItem::query()->create(array_merge($item, [
                    'food99_order_id' => $orderDbId,
                ]));
            }

            return $orderDbId;
        });
    }
}
