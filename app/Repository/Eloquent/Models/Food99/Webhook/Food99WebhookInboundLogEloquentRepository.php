<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Webhook;

use App\Models\Food99\Webhook\Food99WebhookInboundLog;
use App\Repository\Contracts\Models\Food99\Webhook\Food99WebhookInboundLogRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

class Food99WebhookInboundLogEloquentRepository extends EloquentRepository implements Food99WebhookInboundLogRepositoryInterface
{
    protected string $connection = 'mysql_marketplace';

    public function __construct(Food99WebhookInboundLog $model)
    {
        parent::__construct($model);
    }

    /**
     * Lista logs de webhook filtrados por shops, event_name e status.
     *
     * @param  array<int, int>  $shopIds  IDs internos das lojas
     * @param  array<int, string>  $eventNames  Filtro de event_name (vazio = todos)
     * @param  array<int, string>  $statuses  Filtro de status (vazio = todos)
     * @param  int  $limit  Maximo de registros
     * @return object Collection de logs
     */
    public function listByShopIdsAndFilters(
        array $shopIds,
        array $eventNames = [],
        array $statuses = [],
        int $limit = 50,
    ): object {
        if ($shopIds === []) {
            return $this->model->newCollection();
        }

        return $this->model
            ->newQuery()
            ->whereIn('food99_shop_id', $shopIds)
            ->when($eventNames !== [], static fn ($q) => $q->whereIn('event_name', $eventNames))
            ->when($statuses !== [], static fn ($q) => $q->whereIn('status', $statuses))
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)))
            ->get();
    }
}
