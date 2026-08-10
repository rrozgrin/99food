<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Catalog;

use App\Models\Food99\Catalog\Food99PublishJob;
use App\Repository\Contracts\Models\Food99\Catalog\Food99PublishJobRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

/**
 * Repositorio Eloquent de logs de publicacao 99Food.
 */
class Food99PublishJobEloquentRepository extends EloquentRepository implements Food99PublishJobRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * @param  Food99PublishJob  $model  Model de log de publicacao 99Food
     */
    public function __construct(Food99PublishJob $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findLatestByShopId(int $food99ShopId, int $limit = 20): object
    {
        return $this->model
            ->newQuery()
            ->where('food99_shop_id', $food99ShopId)
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get();
    }
}
