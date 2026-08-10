<?php

declare(strict_types=1);

namespace App\Services\Food99\Webhook;

use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Webhook\Food99WebhookInboundLogRepositoryInterface;

class Food99WebhookLogService
{
    public function __construct(
        private readonly Food99ShopRepositoryInterface $shopRepository,
        private readonly Food99WebhookInboundLogRepositoryInterface $webhookInboundLogRepository,
    ) {}

    /**
     * Lista logs de webhook recebidos, escopado pelo id_cadastro do usuario logado.
     *
     * @param  array<int, string>  $eventNames  Filtro de event_name (vazio = todos)
     * @param  array<int, string>  $statuses  Filtro de status (vazio = todos)
     * @return array<string, mixed>
     */
    public function listLogs(
        int $idCadastro,
        array $eventNames = [],
        array $statuses = [],
        int $limit = 50,
        ?string $appShopId = null,
    ): array {
        $shops = $this->shopRepository->findOwnedByIdCadastro($idCadastro);
        $shopIds = [];
        if (is_object($shops)) {
            $shopIds = $shops
                ->when(is_string($appShopId) && trim($appShopId) !== '', static function ($collection) use ($appShopId) {
                    return $collection->where('app_shop_id', trim((string) $appShopId));
                })
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        if ($shopIds === []) {
            return [
                'id_cadastro' => $idCadastro,
                'app_shop_id' => $appShopId,
                'event_names' => array_values($eventNames),
                'statuses' => array_values($statuses),
                'logs' => [],
                'total' => 0,
            ];
        }

        $logsCollection = $this->webhookInboundLogRepository->listByShopIdsAndFilters(
            shopIds: $shopIds,
            eventNames: $eventNames,
            statuses: $statuses,
            limit: $limit,
        );

        $logs = collect($logsCollection ?? [])
            ->map(static function (object $log): array {
                return [
                    'id' => (int) $log->id,
                    'food99_shop_id' => is_numeric($log->food99_shop_id) ? (int) $log->food99_shop_id : null,
                    'event_name' => $log->event_name,
                    'request_id' => $log->request_id,
                    'status' => $log->status,
                    'error_message' => $log->error_message,
                    'processed_at' => $log->processed_at?->toDateTimeString(),
                    'created_at' => $log->created_at?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();

        return [
            'id_cadastro' => $idCadastro,
            'app_shop_id' => $appShopId,
            'event_names' => array_values($eventNames),
            'statuses' => array_values($statuses),
            'total' => count($logs),
            'logs' => $logs,
        ];
    }
}
