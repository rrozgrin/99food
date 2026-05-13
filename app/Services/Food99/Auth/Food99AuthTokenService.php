<?php

declare(strict_types=1);

namespace App\Services\Food99\Auth;

use Carbon\Carbon;
use App\Exceptions\ApiException;
use App\Services\Food99\Traits\InteractsWithFood99Api;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;

/**
 * Serviço responsável pelo ciclo de vida do token 99Food por loja.
 */
class Food99AuthTokenService
{
    use InteractsWithFood99Api;

    public function __construct(
        private readonly Food99StoreTokenRepositoryInterface $storeTokenRepository,
    ) {}

    /**
     * Busca token com fallback para refresh quando necessário.
     *
     * @return array<string, mixed>
     */
    public function retrieveTokenWithFallback(string $appShopId): array
    {
        try {
            return $this->fetchAndPersistToken(
                appShopId: $appShopId,
                refresh: false,
            );
        } catch (ApiException $exception) {
            if ((int) $exception->getCode() !== 422) {
                throw $exception;
            }

            return $this->fetchAndPersistToken(
                appShopId: $appShopId,
                refresh: true,
            );
        }
    }

    /**
     * Força refresh + persistência do token.
     *
     * @return array<string, mixed>
     */
    public function refreshAndPersistToken(string $appShopId): array
    {
        return $this->fetchAndPersistToken(
            appShopId: $appShopId,
            refresh: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAndPersistToken(string $appShopId, bool $refresh): array
    {
        $query = $this->buildTokenQuery($appShopId);
        $refreshResponse = null;

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
            candidates: ['data.auth_token'],
        );

        if ($authToken === null || $authToken === '') {
            throw new ApiException(
                msg: 'Nao foi possivel identificar o auth_token retornado pela 99Food.',
                code: 502,
            );
        }

        $tokenExpirationTime = $this->extractFirstString(
            payload: $response,
            candidates: ['data.token_expiration_time'],
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
     * @return array<string, string>
     */
    private function buildTokenQuery(string $appShopId): array
    {
        return array_merge($this->food99Credentials(), [
            'app_shop_id' => $appShopId,
        ]);
    }

    private function parseTimestampToDateTime(?string $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === '' || ! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp)->toDateTimeString();
    }
}
