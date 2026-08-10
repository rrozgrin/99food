<?php

declare(strict_types=1);

namespace App\Services\Food99\Auth;

use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Services\Food99\Traits\InteractsWithFood99Api;
use Throwable;

/**
 * Serviço responsável por sincronizar dados da loja autorizada com a 99Food.
 */
class Food99ShopSyncService
{
    use InteractsWithFood99Api;

    public function __construct(
        private readonly Food99ShopRepositoryInterface $shopRepository,
        private readonly Food99AuthShopService $shopService,
    ) {}

    /**
     * Atualiza dados locais da loja após autorização/token válido.
     *
     * @return array<string, mixed>
     */
    public function synchronizeOwnedShopAfterAuthorization(object $shop, string $authToken): array
    {
        try {
            $response = $this->food99Request(
                method: 'GET',
                path: '/v1/shop/shop/detail',
                query: ['auth_token' => $authToken],
            );
        } catch (Throwable $throwable) {
            return array_merge(
                $this->shopService->mapOwnedShop($shop),
                ['shop_sync_error' => $throwable->getMessage()],
            );
        }

        $detail = data_get($response, 'data');
        if (! is_array($detail)) {
            $detail = $response;
        }

        $updatePayload = $this->shopService->filterShopColumns([
            'food99_shop_id' => $this->extractFirstString(
                payload: $detail,
                candidates: ['shop_id', 'food99_shop_id'],
            ),
            'name' => $this->extractFirstString(
                payload: $detail,
                candidates: ['shop_name', 'name'],
            ),
            'binding_status' => 'bound',
            'auth_status' => 'active',
            'last_synced_at' => now(),
            'updated_at' => now(),
        ]);

        if ($updatePayload !== []) {
            $this->shopRepository->update($updatePayload, (int) data_get($shop, 'id'));
        }

        $freshShop = $this->shopRepository->find((int) data_get($shop, 'id'));

        return array_merge(
            $this->shopService->mapOwnedShop(is_object($freshShop) ? $freshShop : $shop),
            ['shop_detail' => $detail],
        );
    }
}
