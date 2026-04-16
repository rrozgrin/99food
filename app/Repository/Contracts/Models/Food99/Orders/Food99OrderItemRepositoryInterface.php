<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Orders;

use App\Repository\Contracts\RepositoryInterface;

interface Food99OrderItemRepositoryInterface extends RepositoryInterface
{
    public function deleteByOrderId(int $food99OrderId): int;

    public function findByOrderId(int $food99OrderId): ?object;
}
