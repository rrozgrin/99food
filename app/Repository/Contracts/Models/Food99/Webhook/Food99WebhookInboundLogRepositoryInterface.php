<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Webhook;

use App\Repository\Contracts\RepositoryInterface;

interface Food99WebhookInboundLogRepositoryInterface extends RepositoryInterface
{
    /**
     * Lista logs de webhook filtrados por shops, event_name e status.
     *
     * @param array<int, int>    $shopIds    IDs internos das lojas
     * @param array<int, string> $eventNames Filtro de event_name (vazio = todos)
     * @param array<int, string> $statuses   Filtro de status (vazio = todos)
     * @param int                $limit      Maximo de registros
     *
     * @return object Collection de logs
     */
    public function listByShopIdsAndFilters(
        array $shopIds,
        array $eventNames = [],
        array $statuses = [],
        int $limit = 50,
    ): object;
}

