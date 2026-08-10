<?php

declare(strict_types=1);

namespace App\Services\Food99\Webhook;

use App\Exceptions\ApiException;
use App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Webhook\Food99WebhookInboundLogRepositoryInterface;
use App\Services\Food99\Orders\Food99OrderErpSyncService;
use App\Services\Traits\WithTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class Food99WebhookService
{
    use WithTransaction;

    public function __construct(
        private readonly Food99AppCredentialRepositoryInterface $appCredentialRepository,
        private readonly Food99ShopRepositoryInterface $shopRepository,
        private readonly Food99WebhookInboundLogRepositoryInterface $webhookInboundLogRepository,
        private readonly Food99OrderRepositoryInterface $orderRepository,
        private readonly Food99OrderErpSyncService $orderErpSyncService,
    ) {}

    /**
     * Processa callback da 99Food e persiste o log de entrada.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string>>  $headers
     */
    public function handle(
        array $payload,
        string $rawBody,
        ?string $didiHeaderSign = null,
        array $headers = [],
    ): void {
        $appId = data_get($payload, 'app_id');
        $appShopId = trim((string) data_get($payload, 'app_shop_id', ''));
        $type = trim((string) data_get($payload, 'type', ''));
        $timestamp = data_get($payload, 'timestamp');
        $eventName = $type !== '' ? $type : 'unknown';
        $requestId = $this->resolveRequestId(
            payload: $payload,
            headers: $headers,
        );

        $log = $this->webhookInboundLogRepository->create([
            'food99_app_credential_id' => is_numeric($appId)
                ? $this->resolveCredentialId((string) $appId)
                : null,
            'food99_shop_id' => ($appShopId !== '' && is_numeric($appId))
                ? $this->resolveShopId((string) $appId, $appShopId)
                : null,
            'event_name' => $eventName,
            'request_id' => $requestId,
            'status' => 'received',
            'headers' => $this->normalizeHeaders($headers),
            'payload' => $payload,
        ]);

        try {
            if (! is_numeric($appId)) {
                throw new ApiException(msg: 'Webhook 99Food sem app_id valido.', code: 422);
            }

            if ($appShopId === '') {
                throw new ApiException(msg: 'Webhook 99Food sem app_shop_id.', code: 422);
            }

            if ($type === '') {
                throw new ApiException(msg: 'Webhook 99Food sem type.', code: 422);
            }

            if (! is_numeric($timestamp)) {
                throw new ApiException(msg: 'Webhook 99Food sem timestamp valido.', code: 422);
            }

            $this->validateSignature(
                rawBody: $rawBody,
                didiHeaderSign: $didiHeaderSign,
            );

            $credentialId = $this->resolveCredentialId((string) $appId);
            if ($credentialId === null) {
                throw new ApiException(msg: 'Credencial 99Food nao encontrada para o app_id do webhook.', code: 422);
            }

            $food99ShopId = $this->resolveShopId(
                appId: (string) $appId,
                appShopId: $appShopId,
            );
            if ($food99ShopId === null) {
                throw new ApiException(msg: 'Loja 99Food nao encontrada para o app_shop_id do webhook.', code: 422);
            }

            $this->persistEventByType(
                eventType: $type,
                payload: $payload,
                credentialId: $credentialId,
                food99ShopId: $food99ShopId,
                appShopId: $appShopId,
                webhookLogId: (int) data_get($log, 'id'),
            );

            $orderId = data_get($payload, 'data.order_id');
            if (! is_numeric($orderId)) {
                $orderId = data_get($payload, 'data.order_info.order_id');
            }

            Log::info('food99.webhook.received', [
                'type' => $type,
                'app_id' => (int) $appId,
                'app_shop_id' => $appShopId,
                'order_id' => is_numeric($orderId) ? (string) $orderId : null,
                'timestamp' => (int) $timestamp,
                'payload' => $payload,
            ]);

            $this->webhookInboundLogRepository->update([
                'status' => 'processed',
                'processed_at' => now(),
            ], (int) data_get($log, 'id'));
        } catch (Throwable $throwable) {
            $this->webhookInboundLogRepository->update([
                'status' => 'failed',
                'processed_at' => now(),
                'error_message' => $throwable->getMessage(),
            ], (int) data_get($log, 'id'));

            throw $throwable;
        }
    }

    private function resolveCredentialId(string $appId): ?int
    {
        $credential = $this->appCredentialRepository->findLatestByAppId($appId);

        return is_object($credential) ? (int) data_get($credential, 'id') : null;
    }

    private function resolveShopId(string $appId, string $appShopId): ?int
    {
        $credential = $this->appCredentialRepository->findLatestByAppId($appId);
        if (! is_object($credential)) {
            return null;
        }

        $shop = $this->shopRepository->findByCredentialAndAppShopId(
            credentialId: (int) data_get($credential, 'id'),
            appShopId: $appShopId,
        );

        return is_object($shop) ? (int) data_get($shop, 'id') : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string>>  $headers
     */
    private function resolveRequestId(array $payload, array $headers): ?string
    {
        $candidates = [
            data_get($payload, 'request_id'),
            data_get($payload, 'requestId'),
            data_get($payload, 'data.request_id'),
            data_get($payload, 'data.requestId'),
            data_get($headers, 'x-request-id.0'),
            data_get($headers, 'request-id.0'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, mixed>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[$name] = array_values(array_filter(
                array_map(
                    static fn ($value): string => trim((string) $value),
                    is_array($values) ? $values : [$values],
                ),
                static fn (string $value): bool => $value !== '',
            ));
        }

        return $normalized;
    }

    private function validateSignature(string $rawBody, ?string $didiHeaderSign): void
    {
        $receivedSign = mb_strtolower(trim((string) $didiHeaderSign));
        if ($receivedSign === '') {
            throw new ApiException(msg: 'Assinatura didi-header-sign ausente.', code: 401);
        }

        $secret = trim((string) config('services.food99.app_secret'));
        if ($secret === '') {
            throw new ApiException(msg: 'FOOD99_APP_SECRET nao configurado.', code: 500);
        }

        $expectedSign = md5($rawBody.$secret);
        if (! hash_equals($expectedSign, $receivedSign)) {
            throw new ApiException(msg: 'Assinatura didi-header-sign invalida.', code: 401);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistEventByType(
        string $eventType,
        array $payload,
        int $credentialId,
        int $food99ShopId,
        string $appShopId,
        int $webhookLogId,
    ): void {
        if ($eventType === 'orderNew') {
            $this->persistOrderNew(
                payload: $payload,
                credentialId: $credentialId,
                food99ShopId: $food99ShopId,
                appShopId: $appShopId,
                webhookLogId: $webhookLogId,
            );

            return;
        }

        if ($eventType === 'orderFinish') {
            $this->persistOrderStatusEvent(
                payload: $payload,
                eventType: $eventType,
                credentialId: $credentialId,
                food99ShopId: $food99ShopId,
                appShopId: $appShopId,
                webhookLogId: $webhookLogId,
            );

            $orderId = $this->extractOrderId($payload);
            if ($orderId !== '') {
                $this->orderErpSyncService->markOrderFinished(
                    food99ShopId: $food99ShopId,
                    orderId: $orderId,
                );
            }

            return;
        }

        if ($eventType === 'orderCancel') {
            $this->persistOrderStatusEvent(
                payload: $payload,
                eventType: $eventType,
                credentialId: $credentialId,
                food99ShopId: $food99ShopId,
                appShopId: $appShopId,
                webhookLogId: $webhookLogId,
            );

            $orderId = $this->extractOrderId($payload);
            if ($orderId !== '') {
                $this->orderErpSyncService->markOrderCanceled(
                    food99ShopId: $food99ShopId,
                    orderId: $orderId,
                );
            }

            return;
        }

        Log::info('food99.webhook.event_not_implemented', [
            'event_type' => $eventType,
            'food99_shop_id' => $food99ShopId,
            'webhook_log_id' => $webhookLogId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistOrderNew(
        array $payload,
        int $credentialId,
        int $food99ShopId,
        string $appShopId,
        int $webhookLogId,
    ): int {
        $orderInfo = data_get($payload, 'data.order_info');
        if (! is_array($orderInfo)) {
            $orderInfo = [];
        }

        $orderId = trim((string) (data_get($orderInfo, 'order_id') ?? data_get($payload, 'data.order_id', '')));
        if ($orderId === '') {
            throw new ApiException(msg: 'Webhook orderNew sem order_id.', code: 422);
        }

        $items = data_get($orderInfo, 'order_items');
        if (! is_array($items)) {
            $items = [];
        }

        $orderId = $this->orderRepository->upsertOrderWithItems(
            orderAttributes: [
                'food99_shop_id' => $food99ShopId,
                'order_id' => $orderId,
            ],
            orderValues: [
                'food99_app_credential_id' => $credentialId,
                'food99_webhook_inbound_log_id' => $webhookLogId,
                'event_type' => 'orderNew',
                'app_shop_id' => $appShopId,
                'status' => $this->toIntOrNull(data_get($orderInfo, 'status')),
                'order_index' => $this->toIntOrNull(data_get($orderInfo, 'order_index')),
                'remark' => $this->toNullableString(data_get($orderInfo, 'remark')),
                'country' => $this->toNullableString(data_get($orderInfo, 'country')),
                'timezone' => $this->toNullableString(data_get($orderInfo, 'timezone')),
                'pay_type' => $this->toIntOrNull(data_get($orderInfo, 'pay_type')),
                'delivery_type' => $this->toIntOrNull(data_get($orderInfo, 'delivery_type')),
                'order_price' => $this->toIntOrNull(data_get($orderInfo, 'price.order_price')),
                'real_price' => $this->toIntOrNull(data_get($orderInfo, 'price.real_price')),
                'real_pay_price' => $this->toIntOrNull(data_get($orderInfo, 'price.real_pay_price')),
                'refund_price' => $this->toIntOrNull(data_get($orderInfo, 'price.refund_price')),
                'customer_name' => $this->toNullableString(data_get($orderInfo, 'receive_address.name')),
                'customer_phone' => $this->toNullableString(data_get($orderInfo, 'receive_address.phone')),
                'create_time' => $this->toDateTimeOrNull(data_get($orderInfo, 'create_time')),
                'pay_time' => $this->toDateTimeOrNull(data_get($orderInfo, 'pay_time')),
                'complete_time' => $this->toDateTimeOrNull(data_get($orderInfo, 'complete_time')),
                'cancel_time' => $this->toDateTimeOrNull(data_get($orderInfo, 'cancel_time')),
                'sync_status' => 'new_order',
                'payload' => $payload,
                'error_message' => null,
            ],
            items: $this->normalizeOrderItems($items),
        );

        if ($orderId <= 0) {
            throw new ApiException(msg: 'Nao foi possivel persistir o pedido orderNew.', code: 500);
        }

        return $orderId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistOrderStatusEvent(
        array $payload,
        string $eventType,
        int $credentialId,
        int $food99ShopId,
        string $appShopId,
        int $webhookLogId,
    ): void {
        $orderId = $this->extractOrderId($payload);
        if ($orderId === '') {
            throw new ApiException(msg: sprintf('Webhook %s sem order_id.', $eventType), code: 422);
        }

        $statusUpdates = [
            'food99_app_credential_id' => $credentialId,
            'food99_webhook_inbound_log_id' => $webhookLogId,
            'event_type' => $eventType,
            'app_shop_id' => $appShopId,
            'error_message' => null,
        ];

        if ($eventType === 'orderFinish') {
            $statusUpdates['complete_time'] = $this->toDateTimeOrNull(data_get($payload, 'timestamp'));
            $statusUpdates['sync_status'] = 'pending_sync';
        }

        if ($eventType === 'orderCancel') {
            $statusUpdates['cancel_time'] = $this->toDateTimeOrNull(data_get($payload, 'timestamp'));
            $statusUpdates['sync_status'] = 'canceled';
        }

        $this->orderRepository->updateOrCreate(
            attributes: [
                'food99_shop_id' => $food99ShopId,
                'order_id' => $orderId,
            ],
            values: $statusUpdates,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOrderId(array $payload): string
    {
        $orderId = trim((string) (data_get($payload, 'data.order_id') ?? data_get($payload, 'data.order_info.order_id', '')));

        return $orderId;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function toDateTimeOrNull(mixed $unixTimestamp): ?Carbon
    {
        if (! is_numeric($unixTimestamp)) {
            return null;
        }

        $timestamp = (int) $unixTimestamp;
        if ($timestamp <= 0) {
            return null;
        }

        return Carbon::createFromTimestampUTC($timestamp)
            ->setTimezone(config('app.timezone', 'America/Sao_Paulo'));
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOrderItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'app_item_id' => $this->toNullableString(data_get($item, 'app_item_id')),
                'app_external_id' => $this->toNullableString(data_get($item, 'app_external_id')),
                'item_name' => $this->toNullableString(data_get($item, 'name')),
                'amount' => max(1, (int) ($this->toIntOrNull(data_get($item, 'amount')) ?? 1)),
                'sku_price' => $this->toIntOrNull(data_get($item, 'sku_price')),
                'total_price' => $this->toIntOrNull(data_get($item, 'total_price')),
                'real_price' => $this->toIntOrNull(data_get($item, 'real_price')),
                'remark' => $this->toNullableString(data_get($item, 'remark')),
                'payload' => $item,
            ];
        }

        return $normalized;
    }
}
