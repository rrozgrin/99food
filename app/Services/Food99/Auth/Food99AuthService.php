<?php

declare(strict_types=1);

namespace App\Services\Food99\Auth;

use Throwable;
use App\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
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
        $shop = $this->createOrReusePendingShop($payload);
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
                'authorization_url',
                'auth_url',
                'url',
                'conteudo.authorization_url',
                'conteudo.auth_url',
                'conteudo.url',
                'data.authorization_url',
                'data.auth_url',
                'data.url',
            ],
        );

        return [
            'app_shop_id' => $appShopId,
            'shop' => $this->mapOwnedShop($shop),
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
        $idCadastro = $this->resolveAuthenticatedIdCadastro();

        $shops = $this->shopRepository->findOwnedByIdCadastro($idCadastro);

        $tokenRowsByAppShopId = collect();
        $tokenRowsByAppShopId = $this->storeTokenRepository
            ->findByAppShopIds(
                $shops
                    ->pluck('app_shop_id')
                    ->filter(static fn ($appShopId): bool => is_string($appShopId) && trim($appShopId) !== '')
                    ->values()
                    ->all(),
            )
            ?->keyBy('app_shop_id') ?? collect();

        return [
            'id_cadastro' => $idCadastro,
            'shops' => $shops
                ->map(function (object $shop) use ($tokenRowsByAppShopId): array {
                    $tokenRow = $tokenRowsByAppShopId->get((string) data_get($shop, 'app_shop_id'));

                    return array_merge(
                        $this->mapOwnedShop($shop),
                        [
                            'token_found' => is_object($tokenRow) && trim((string) data_get($tokenRow, 'auth_token')) !== '',
                            'token_expires_at' => data_get($tokenRow, 'expires_at'),
                            'token_last_retrieved_at' => data_get($tokenRow, 'last_retrieved_at'),
                            'token_last_refreshed_at' => data_get($tokenRow, 'last_refreshed_at'),
                        ],
                    );
                })
                ->values()
                ->all(),
        ];
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
        $shop = $this->resolveOwnedShopByAppShopId($appShopId);

        try {
            $tokenData = $this->fetchAndPersistToken(
                appShopId: $appShopId,
                refresh: false,
            );
        } catch (ApiException $exception) {
            // Contrato externo: quando ainda nao ha token valido, pode ser necessario
            // executar refresh antes do get. Fazemos fallback automatico para reduzir falhas.
            if ((int) $exception->getCode() !== 422) {
                throw $exception;
            }

            $tokenData = $this->fetchAndPersistToken(
                appShopId: $appShopId,
                refresh: true,
            );
        }

        $tokenData['shop'] = $this->synchronizeOwnedShopAfterAuthorization(
            shop: $shop,
            authToken: (string) $tokenData['auth_token'],
        );

        return $tokenData;
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
        $shop = $this->resolveOwnedShopByAppShopId($appShopId);
        $tokenData = $this->fetchAndPersistToken(
            appShopId: $appShopId,
            refresh: true,
        );

        $tokenData['shop'] = $this->synchronizeOwnedShopAfterAuthorization(
            shop: $shop,
            authToken: (string) $tokenData['auth_token'],
        );

        return $tokenData;
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
        $this->resolveOwnedShopByAppShopId($appShopId);

        return $this->storeTokenRepository->findByAppShopId($appShopId);
    }

    /**
     * Busca token na 99Food (get/refresh) e persiste em mysql_marketplace.
     *
     * @param string $appShopId ID externo da loja na 99Food
     * @param bool   $refresh   Se true chama endpoint de refresh
     *
     * @return array<string, mixed> Dados normalizados do token
     */
    private function fetchAndPersistToken(string $appShopId, bool $refresh): array
    {
        $query = array_merge($this->food99Credentials(), [
            'app_shop_id' => $appShopId,
        ]);

        $refreshResponse = null;

        // No contrato da 99Food, o refresh nao retorna token.
        // Ele apenas invalida/gera novo token e exige nova chamada ao endpoint get.
        if ($refresh) {
            $refreshResponse = $this->food99Request(
                method: 'GET',
                path: '/v1/auth/authtoken/refresh',
                query: $query,
            );
        }

        $response = $this->food99Request(
            method: 'GET',
            path: '/v1/auth/authtoken/get',
            query: $query,
        );

        $authToken = $this->extractFirstString(
            payload: $response,
            candidates: [
                'auth_token',
                'token',
                'conteudo.auth_token',
                'conteudo.token',
                'data.auth_token',
                'data.token',
            ],
        );

        if ($authToken === null || $authToken === '') {
            throw new ApiException(
                msg: 'Nao foi possivel identificar o auth_token retornado pela 99Food.',
                code: 502,
            );
        }

        $tokenExpirationTime = $this->extractFirstString(
            payload: $response,
            candidates: [
                'token_expiration_time',
                'expiration_time',
                'expires_at',
                'conteudo.token_expiration_time',
                'conteudo.expiration_time',
                'data.token_expiration_time',
                'data.expiration_time',
                'data.expires_at',
            ],
        );

        $persisted = $this->storeTokenRepository->upsertByAppShopId(
            appShopId: $appShopId,
            payload: [
                'auth_token' => $authToken,
                'expires_at' => $this->parseTimestampToDateTime($tokenExpirationTime),
                'last_refreshed_at' => $refresh ? now() : null,
                'last_retrieved_at' => now(),
                'payload' => $response,
            ],
        );

        return [
            'app_shop_id' => $appShopId,
            'auth_token' => $authToken,
            'expires_at' => $this->parseTimestampToDateTime($tokenExpirationTime),
            'persisted_id' => $persisted->id ?? null,
            'refresh_response' => $refreshResponse,
            'response' => $response,
        ];
    }

    /**
     * Extrai a primeira string nao vazia de uma lista de caminhos no payload.
     *
     * @param array<string, mixed> $payload    Payload completo
     * @param array<int, string>   $candidates Lista de caminhos para tentativa
     *
     * @return string|null Valor encontrado ou null
     */
    private function extractFirstString(array $payload, array $candidates): ?string
    {
        foreach ($candidates as $path) {
            $value = data_get($payload, $path);

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Converte timestamp da API para datetime no formato aceito pelo banco.
     *
     * @param string|null $timestamp Timestamp em segundos retornado pela 99Food
     *
     * @return string|null Data e hora no formato Y-m-d H:i:s ou null
     */
    private function parseTimestampToDateTime(?string $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === '' || ! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp)->toDateTimeString();
    }

    /**
     * Cria ou reaproveita o mapeamento local da loja antes da autorizacao externa.
     *
     * @param array<string, mixed> $payload Payload recebido da request
     *
     * @return object Registro local da loja
     */
    private function createOrReusePendingShop(array $payload): object
    {
        $idCadastro = $this->resolveAuthenticatedIdCadastro();
        $appShopId = $this->normalizeAppShopId(
            is_string(data_get($payload, 'app_shop_id')) ? (string) data_get($payload, 'app_shop_id') : null,
            $idCadastro,
        );

        $credentialId = $this->resolveOrCreateConfiguredAppCredentialId();

        $existing = $this->shopRepository->findByCredentialAndAppShopId($credentialId, $appShopId);
        if (is_object($existing) && (int) data_get($existing, 'id_cadastro') !== $idCadastro) {
            throw new ApiException(
                msg: 'O app_shop_id informado ja esta vinculado a outro cliente no hub.',
                code: 409,
            );
        }

        $shopName = $this->extractPreferredShopName($payload);
        $timestamp = now();
        $insertPayload = $this->filterByExistingColumns('food99_shops', [
            'food99_app_credential_id' => $credentialId,
            'id_cadastro' => $idCadastro,
            'app_shop_id' => $appShopId,
            'name' => $shopName,
            'binding_status' => 'pending',
            'auth_status' => 'pending',
            'updated_at' => $timestamp,
            'created_at' => $timestamp,
        ]);

        if (is_object($existing)) {
            unset($insertPayload['created_at']);

            if (
                is_string(data_get($existing, 'binding_status'))
                && trim((string) data_get($existing, 'binding_status')) !== ''
            ) {
                unset($insertPayload['binding_status']);
            }

            if (
                is_string(data_get($existing, 'auth_status'))
                && trim((string) data_get($existing, 'auth_status')) !== ''
            ) {
                unset($insertPayload['auth_status']);
            }

            if ($shopName === null) {
                unset($insertPayload['name']);
            }

            if ($insertPayload !== []) {
                $this->shopRepository->update($insertPayload, (int) data_get($existing, 'id'));
            }

            $existing = $this->shopRepository->find((int) data_get($existing, 'id'));
        } else {
            $created = $this->shopRepository->create($insertPayload);
            $existing = $this->shopRepository->find((int) data_get($created, 'id'));
        }

        return is_object($existing)
            ? $existing
            : $this->resolveOwnedShopByAppShopId($appShopId);
    }

    /**
     * Atualiza dados locais da loja apos autorizacao/token valido.
     *
     * @param object $shop      Registro local da loja
     * @param string $authToken Token da loja na 99Food
     *
     * @return array<string, mixed> Dados locais e sincronizacao externa
     */
    private function synchronizeOwnedShopAfterAuthorization(object $shop, string $authToken): array
    {
        try {
            $response = $this->food99Request(
                method: 'GET',
                path: '/v1/shop/shop/detail',
                query: ['auth_token' => $authToken],
            );
        } catch (Throwable $throwable) {
            return array_merge(
                $this->mapOwnedShop($shop),
                ['shop_sync_error' => $throwable->getMessage()],
            );
        }

        $detail = data_get($response, 'data');
        if (! is_array($detail)) {
            $detail = $response;
        }

        $updatePayload = $this->filterByExistingColumns('food99_shops', [
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
            $this->mapOwnedShop(is_object($freshShop) ? $freshShop : $shop),
            ['shop_detail' => $detail],
        );
    }

    /**
     * Resolve loja local do cliente logado a partir do app_shop_id.
     *
     * @param string $appShopId app_shop_id informado na API
     *
     * @return object Registro da loja
     */
    private function resolveOwnedShopByAppShopId(string $appShopId): object
    {
        $idCadastro = $this->resolveAuthenticatedIdCadastro();

        $shop = $this->shopRepository->findOwnedByAppShopId($idCadastro, $appShopId);

        if (! is_object($shop)) {
            throw new ApiException(
                msg: 'Loja nao encontrada para o cliente logado e app_shop_id informado.',
                code: 404,
            );
        }

        return $shop;
    }

    /**
     * Resolve ou cria o registro local da credencial configurada para a 99Food.
     *
     * @return int ID da credencial no marketplace
     */
    private function resolveOrCreateConfiguredAppCredentialId(): int
    {
        $credentials = $this->food99Credentials();

        $existing = $this->appCredentialRepository->findLatestByAppId($credentials['app_id']);

        $payload = $this->filterByExistingColumns('food99_app_credentials', [
            'name' => config('app.name', 'ERP Hub'),
            'environment' => $this->detectConfiguredEnvironment(),
            'app_id' => $credentials['app_id'],
            'app_secret' => $credentials['app_secret'],
            'base_url' => (string) config('services.food99.base_url'),
            'status' => 'active',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        if (is_object($existing)) {
            $credentialId = (int) data_get($existing, 'id');
            unset($payload['created_at']);

            if ($payload !== []) {
                $this->appCredentialRepository->update($payload, $credentialId);
            }

            return $credentialId;
        }

        return (int) data_get($this->appCredentialRepository->create($payload), 'id');
    }

    /**
     * Obtém o id_cadastro do usuário autenticado.
     *
     * @return int ID do cadastro logado
     */
    private function resolveAuthenticatedIdCadastro(): int
    {
        $idCadastro = $this->usuarioLogado->getIdCadastroLogado();
        if (! is_numeric($idCadastro) || (int) $idCadastro <= 0) {
            throw new ApiException(
                msg: 'Nao foi possivel identificar o id_cadastro do usuario logado.',
                code: 403,
            );
        }

        return (int) $idCadastro;
    }

    /**
     * Normaliza o app_shop_id usado na 99Food.
     *
     * @param string|null $appShopId  Valor informado pela request
     * @param int         $idCadastro ID do cliente logado
     *
     * @return string app_shop_id normalizado
     */
    private function normalizeAppShopId(?string $appShopId, int $idCadastro): string
    {
        $raw = trim((string) ($appShopId ?? ''));
        if ($raw === '') {
            $raw = 'wc-' . $idCadastro;
        }

        $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $raw);
        $normalized = preg_replace('/-+/', '-', (string) $normalized);
        $normalized = trim((string) $normalized, '-_');

        if ($normalized === '') {
            throw new ApiException(
                msg: 'Nao foi possivel gerar um app_shop_id valido para a loja.',
                code: 422,
            );
        }

        return mb_substr($normalized, 0, 255);
    }

    /**
     * Retorna o nome preferencial da loja a partir do payload da request.
     *
     * @param array<string, mixed> $payload Payload recebido
     *
     * @return string|null Nome da loja quando informado
     */
    private function extractPreferredShopName(array $payload): ?string
    {
        foreach (['shop_name', 'name', 'store_name'] as $candidate) {
            $value = data_get($payload, $candidate);
            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 255);
            }
        }

        return null;
    }

    /**
     * Filtra payload mantendo apenas colunas que existem fisicamente na tabela.
     *
     * @param string              $table   Tabela do mysql_marketplace
     * @param array<string, mixed> $payload Dados candidatos
     *
     * @return array<string, mixed> Payload filtrado
     */
    private function filterByExistingColumns(string $table, array $payload): array
    {
        try {
            $columnSet = array_fill_keys(
                Schema::connection('mysql_marketplace')->getColumnListing($table),
                true,
            );
        } catch (Throwable) {
            return array_filter(
                $payload,
                static fn (mixed $value): bool => $value !== null,
            );
        }

        return array_filter(
            $payload,
            static fn (mixed $value, string $column): bool => array_key_exists($column, $columnSet) && $value !== null,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Detecta o ambiente configurado da integracao 99Food.
     *
     * @return string Nome do ambiente
     */
    private function detectConfiguredEnvironment(): string
    {
        $baseUrl = mb_strtolower((string) config('services.food99.base_url'));

        return str_contains($baseUrl, 'sandbox') ? 'sandbox' : 'production';
    }

    /**
     * Normaliza o retorno da loja local para a API.
     *
     * @param object $shop Registro local
     *
     * @return array<string, mixed> Loja no formato de resposta
     */
    private function mapOwnedShop(object $shop): array
    {
        return [
            'id' => (int) data_get($shop, 'id'),
            'id_cadastro' => is_numeric(data_get($shop, 'id_cadastro'))
                ? (int) data_get($shop, 'id_cadastro')
                : null,
            'app_shop_id' => data_get($shop, 'app_shop_id'),
            'food99_shop_id' => data_get($shop, 'food99_shop_id'),
            'name' => data_get($shop, 'name'),
            'binding_status' => data_get($shop, 'binding_status'),
            'auth_status' => data_get($shop, 'auth_status'),
            'last_synced_at' => data_get($shop, 'last_synced_at'),
        ];
    }
}
