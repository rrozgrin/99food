<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Orders;

use App\Models\Food99\Orders\Food99OrderItem;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderItemRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

class Food99OrderItemEloquentRepository extends EloquentRepository implements Food99OrderItemRepositoryInterface
{
    protected string $connection = 'mysql_marketplace';

    public function __construct(Food99OrderItem $model)
    {
        parent::__construct($model);
    }

    public function deleteByOrderId(int $food99OrderId): int
    {
        return $this->model
            ->newQuery()
            ->where('food99_order_id', $food99OrderId)
            ->delete();
    }

    public function findByOrderId(int $food99OrderId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_order_id', $food99OrderId)
            ->orderBy('id')
            ->get();
    }
}
