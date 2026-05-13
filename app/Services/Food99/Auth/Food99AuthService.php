<?php

declare(strict_types=1);

namespace App\Services\Food99\Auth;

use App\Services\Auth\UsuarioLogadoService;
use App\Services\Food99\Traits\InteractsWithFood99Api;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;

/**
 * Servico de autenticacao da 99Food.
 *
 * Responsabilidades:
 * - Gerar URL de vinculacao de loja (authorization page)
 * - Obter token por loja
 * - Renovar token por loja
 * - Persistir token em mysql_marketplace
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class Food99AuthService
{
    use InteractsWithFood99Api;

    private ?Food99AuthShopService $shopService = null;
    private ?Food99AuthTokenService $tokenService = null;
    private ?Food99ShopSyncService $shopSyncService = null;

    /**
     * Inicializa o servico com o repositorio de tokens por loja.
     *
     * @param Food99StoreTokenRepositoryInterface $storeTokenRepository Repositorio de token por loja
     */
    public function __construct(
        private readonly Food99StoreTokenRepositoryInterface $storeTokenRepository,
        private readonly UsuarioLogadoService $usuarioLogado,
        private readonly Food99ShopRepositoryInterface $shopRepository,
        private readonly Food99AppCredentialRepositoryInterface $appCredentialRepository,
    ) {}

    /**
     * Obtem a URL da pagina de autorizacao da 99Food para vincular loja.
     *
     * @param array<string, mixed> $payload Payload complementar exigido pela 99Food
     *
     * @return array<string, mixed> URL resolvida e resposta bruta da API externa
     */
    public function getAuthorizationUrl(array $payload = []): array
    {
        $shop = $this->shopService()->createOrReusePendingShop(
            payload: $payload,
            credentials: $this->food99Credentials(),
        );
        $appShopId = (string) data_get($shop, 'app_shop_id');
        $requestPayload = $payload;
        $requestPayload['app_shop_id'] = $appShopId;

        $response = $this->food99Request(
            method: 'POST',
            path: '/v1/auth/authorizationpage/getUrl',
            json: array_merge($requestPayload, $this->food99Credentials()),
        );

        $authorizationUrl = $this->extractFirstString(
            payload: $response,
            candidates: [
                'data.url',
            ],
        );

        return [
            'app_shop_id' => $appShopId,
            'shop' => $this->shopService()->mapOwnedShop($shop),
            'authorization_url' => $authorizationUrl,
            'response' => $response,
        ];
    }

    /**
     * Lista lojas 99Food do cliente logado.
     *
     * @return array<string, mixed> Lojas locais vinculadas ao usuario atual
     */
    public function listOwnedShops(): array
    {
        return $this->shopService()->listOwnedShopsWithTokenMetadata();
    }

    /**
     * Obtem e persiste o auth_token da 99Food para uma loja.
     *
     * @param string $appShopId ID externo da loja na 99Food
     *
     * @return array<string, mixed> Token e metadados retornados pela API externa
     */
    public function getTokenByStore(string $appShopId): array
    {
        $shop = $this->shopService()->resolveOwnedShopByAppShopId($appShopId);
        $tokenData = $this->tokenService()->retrieveTokenWithFallback(appShopId: $appShopId);

        return $this->attachShopSyncData(shop: $shop, tokenData: $tokenData);
    }

    /**
     * Renova e persiste o auth_token da 99Food para uma loja.
     *
     * @param string $appShopId ID externo da loja na 99Food
     *
     * @return array<string, mixed> Token renovado e metadados retornados pela API externa
     */
    public function refreshTokenByStore(string $appShopId): array
    {
        $shop = $this->shopService()->resolveOwnedShopByAppShopId($appShopId);
        $tokenData = $this->tokenService()->refreshAndPersistToken(appShopId: $appShopId);

        return $this->attachShopSyncData(shop: $shop, tokenData: $tokenData);
    }

    /**
     * Retorna token armazenado localmente para uma loja.
     *
     * @param string $appShopId ID externo da loja na 99Food
     *
     * @return object|null Registro local do token
     */
    public function getStoredTokenByStore(string $appShopId): ?object
    {
        $this->shopService()->resolveOwnedShopByAppShopId($appShopId);

        return $this->storeTokenRepository->findByAppShopId($appShopId);
    }

    /**
     * Anexa dados de sincronização da loja ao retorno de token.
     *
     * @param object               $shop      Loja local
     * @param array<string, mixed> $tokenData Resposta de token
     *
     * @return array<string, mixed> Resposta final com dados da loja
     */
    private function attachShopSyncData(object $shop, array $tokenData): array
    {
        $tokenData['shop'] = $this->shopSyncService()->synchronizeOwnedShopAfterAuthorization(
            shop: $shop,
            authToken: (string) $tokenData['auth_token'],
        );

        return $tokenData;
    }

    private function shopService(): Food99AuthShopService
    {
        if ($this->shopService instanceof Food99AuthShopService) {
            return $this->shopService;
        }

        $this->shopService = new Food99AuthShopService(
            storeTokenRepository: $this->storeTokenRepository,
            usuarioLogado: $this->usuarioLogado,
            shopRepository: $this->shopRepository,
            appCredentialRepository: $this->appCredentialRepository,
        );

        return $this->shopService;
    }

    private function tokenService(): Food99AuthTokenService
    {
        if ($this->tokenService instanceof Food99AuthTokenService) {
            return $this->tokenService;
        }

        $this->tokenService = new Food99AuthTokenService(
            storeTokenRepository: $this->storeTokenRepository,
        );

        return $this->tokenService;
    }

    private function shopSyncService(): Food99ShopSyncService
    {
        if ($this->shopSyncService instanceof Food99ShopSyncService) {
            return $this->shopSyncService;
        }

        $this->shopSyncService = new Food99ShopSyncService(
            shopRepository: $this->shopRepository,
            shopService: $this->shopService(),
        );

        return $this->shopSyncService;
    }
}
