<?php

declare(strict_types=1);

namespace App\Services\Food99\Auth;

use App\Exceptions\ApiException;
use App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;
use App\Services\Auth\UsuarioLogadoService;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Serviço de domínio para ownership e metadados de lojas 99Food.
 */
class Food99AuthShopService
{
    /**
     * Cache local de colunas por tabela para reduzir introspecções repetidas.
     *
     * @var array<string, array<string, bool>>
     */
    private array $tableColumnsCache = [];

    public function __construct(
        private readonly Food99StoreTokenRepositoryInterface $storeTokenRepository,
        private readonly UsuarioLogadoService $usuarioLogado,
        private readonly Food99ShopRepositoryInterface $shopRepository,
        private readonly Food99AppCredentialRepositoryInterface $appCredentialRepository,
    ) {}

    /**
     * Lista lojas do cliente autenticado com metadados de token.
     *
     * @return array<string, mixed>
     */
    public function listOwnedShopsWithTokenMetadata(): array
    {
        $idCadastro = $this->resolveAuthenticatedIdCadastro();
        $shops = $this->shopRepository->findOwnedByIdCadastro($idCadastro);

        return [
            'id_cadastro' => $idCadastro,
            'shops' => $this->enrichShopsWithTokenMetadata($shops),
        ];
    }

    /**
     * Resolve loja por ownership.
     */
    public function resolveOwnedShopByAppShopId(string $appShopId): object
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
     * Cria ou reaproveita mapeamento local antes da autorização externa.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{app_id:string,app_secret:string}  $credentials
     */
    public function createOrReusePendingShop(array $payload, array $credentials): object
    {
        $idCadastro = $this->resolveAuthenticatedIdCadastro();
        $appShopId = $this->normalizeAppShopId(
            is_string(data_get($payload, 'app_shop_id')) ? (string) data_get($payload, 'app_shop_id') : null,
            $idCadastro,
        );

        $credentialId = $this->resolveOrCreateConfiguredAppCredentialId($credentials);

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

            if (is_string(data_get($existing, 'binding_status')) && trim((string) data_get($existing, 'binding_status')) !== '') {
                unset($insertPayload['binding_status']);
            }

            if (is_string(data_get($existing, 'auth_status')) && trim((string) data_get($existing, 'auth_status')) !== '') {
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
     * Normaliza loja para resposta da API.
     *
     * @return array<string, mixed>
     */
    public function mapOwnedShop(object $shop): array
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

    /**
     * Filtra payload para colunas existentes da tabela de lojas.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function filterShopColumns(array $payload): array
    {
        return $this->filterByExistingColumns('food99_shops', $payload);
    }

    /**
     * @param  object  $shops  Coleção de lojas
     * @return array<int, array<string, mixed>>
     */
    private function enrichShopsWithTokenMetadata(object $shops): array
    {
        $tokenRowsByAppShopId = $this->storeTokenRepository
            ->findByAppShopIds(
                $shops
                    ->pluck('app_shop_id')
                    ->filter(static fn ($appShopId): bool => is_string($appShopId) && trim($appShopId) !== '')
                    ->values()
                    ->all(),
            )
            ?->keyBy('app_shop_id') ?? collect();

        return $shops
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
            ->all();
    }

    /**
     * @param  array{app_id:string,app_secret:string}  $credentials
     */
    private function resolveOrCreateConfiguredAppCredentialId(array $credentials): int
    {
        $existing = $this->appCredentialRepository->findLatestByAppId($credentials['app_id']);

        $payload = $this->filterByExistingColumns('food99_app_credentials', [
            'name' => config('app.name', 'ERP'),
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

    private function normalizeAppShopId(?string $appShopId, int $idCadastro): string
    {
        $raw = trim((string) ($appShopId ?? ''));
        if ($raw === '') {
            $raw = 'wc-'.$idCadastro;
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
     * @param  array<string, mixed>  $payload
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterByExistingColumns(string $table, array $payload): array
    {
        $columnSet = $this->resolveTableColumnSet($table);
        if ($columnSet === null) {
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
     * @return array<string, bool>|null
     */
    private function resolveTableColumnSet(string $table): ?array
    {
        if (array_key_exists($table, $this->tableColumnsCache)) {
            return $this->tableColumnsCache[$table];
        }

        try {
            $columnSet = array_fill_keys(
                Schema::connection('mysql_marketplace')->getColumnListing($table),
                true,
            );
        } catch (Throwable) {
            return null;
        }

        $this->tableColumnsCache[$table] = $columnSet;

        return $columnSet;
    }

    private function detectConfiguredEnvironment(): string
    {
        $baseUrl = mb_strtolower((string) config('services.food99.base_url'));

        return str_contains($baseUrl, 'sandbox') ? 'sandbox' : 'production';
    }
}
