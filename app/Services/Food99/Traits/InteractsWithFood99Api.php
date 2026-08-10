<?php

declare(strict_types=1);

namespace App\Services\Food99\Traits;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Trait com metodos compartilhados de comunicacao HTTP com a API da 99Food.
 *
 * Centraliza:
 * - Execucao de chamadas HTTP (GET/POST) com tratamento de erro
 * - Tratamento de erros de negocio (errno != 0) retornados pela API
 * - Resolucao das credenciais de aplicacao (app_id/app_secret)
 *
 * Deve ser usada por qualquer Service que precise interagir com a API
 * externa da 99Food (auth, catalogo, pedidos, loja, etc.).
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
trait InteractsWithFood99Api
{
    /**
     * Executa chamada HTTP na API da 99Food.
     *
     * @param  string  $method  Metodo HTTP (GET/POST)
     * @param  string  $path  Endpoint da API externa
     * @param  array<string, mixed>  $query  Query string params
     * @param  array<string, mixed>  $json  Body JSON
     * @return array<string, mixed> Resposta decodificada em array
     */
    protected function food99Request(
        string $method,
        string $path,
        array $query = [],
        array $json = [],
    ): array {
        return $this->food99RequestInternal(
            method: $method,
            path: $path,
            query: $query,
            json: $json,
            allowRefreshRetry: true,
        );
    }

    /**
     * Executa chamada HTTP com suporte opcional a retry apos refresh de autorizacao.
     *
     * @param  string  $method  Metodo HTTP (GET/POST)
     * @param  string  $path  Endpoint da API externa
     * @param  array<string, mixed>  $query  Query string params
     * @param  array<string, mixed>  $json  Body JSON
     * @param  bool  $allowRefreshRetry  Se true, tenta refresh automatico uma vez
     * @return array<string, mixed> Resposta decodificada em array
     */
    private function food99RequestInternal(
        string $method,
        string $path,
        array $query,
        array $json,
        bool $allowRefreshRetry,
    ): array {
        $baseUrl = (string) config('services.food99.base_url');
        $timeout = (int) config('services.food99.timeout', 20);

        if ($baseUrl === '') {
            throw new ApiException(
                msg: 'FOOD99_BASE_URL nao configurado.',
                code: 500,
            );
        }

        $httpResponse = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout($timeout)
            ->send(
                method: mb_strtoupper($method),
                url: $path,
                options: [
                    'query' => $query,
                    'json' => $json,
                ],
            );

        $this->ensureFood99HttpSuccess($httpResponse);

        $payload = $httpResponse->json();

        if (! is_array($payload)) {
            throw new ApiException(
                msg: 'Resposta invalida da 99Food. JSON nao retornado.',
                code: 502,
            );
        }

        $errno = data_get($payload, 'errno');
        if (is_numeric($errno) && (int) $errno !== 0) {
            $message = data_get($payload, 'msg')
                ?? data_get($payload, 'message')
                ?? data_get($payload, 'errmsg')
                ?? data_get($payload, 'error_msg');

            if (
                $allowRefreshRetry
                && $this->shouldRetryWithAuthorizationRefresh(path: $path, message: is_string($message) ? $message : '')
            ) {
                $appShopId = $this->resolveAppShopIdFromRequest(query: $query, json: $json);

                if ($appShopId !== null) {
                    $refreshedAuthToken = $this->refreshAuthorizationAndGetToken(appShopId: $appShopId);

                    if (array_key_exists('auth_token', $query)) {
                        $query['auth_token'] = $refreshedAuthToken;
                    }

                    if (array_key_exists('auth_token', $json)) {
                        $json['auth_token'] = $refreshedAuthToken;
                    }

                    return $this->food99RequestInternal(
                        method: $method,
                        path: $path,
                        query: $query,
                        json: $json,
                        allowRefreshRetry: false,
                    );
                }
            }

            if (! is_string($message) || trim($message) === '') {
                $payloadPreview = json_encode(
                    value: $payload,
                    flags: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );

                $message = sprintf(
                    'A API da 99Food retornou erro de negocio. errno=%s payload=%s',
                    (string) $errno,
                    is_string($payloadPreview) ? $payloadPreview : '[payload_json_invalido]',
                );
            }

            throw new ApiException(
                msg: (string) $message,
                code: 422,
            );
        }

        return $payload;
    }

    /**
     * Determina se o erro recebido indica autorizacao expirada para retry automatico.
     *
     * @param  string  $path  Endpoint original chamado
     * @param  string  $message  Mensagem de erro retornada pela API
     * @return bool True quando deve tentar refresh e retry
     */
    private function shouldRetryWithAuthorizationRefresh(string $path, string $message): bool
    {
        if (str_contains($path, '/v1/auth/')) {
            return false;
        }

        $normalizedMessage = mb_strtolower(trim($message));
        if ($normalizedMessage === '') {
            return false;
        }

        return str_contains($normalizedMessage, 'authorization information has expired')
            || str_contains($normalizedMessage, 'authorization has expired')
            || str_contains($normalizedMessage, 'auth token expired')
            || str_contains($normalizedMessage, 'auth_token expired')
            || str_contains($normalizedMessage, 'token expired')
            || str_contains($normalizedMessage, 'auth token is incorrect or has expired')
            || (str_contains($normalizedMessage, 'auth token') && str_contains($normalizedMessage, 'has expired'));
    }

    /**
     * Resolve app_shop_id a partir do query/body da requisicao original.
     *
     * @param  array<string, mixed>  $query  Query string enviada
     * @param  array<string, mixed>  $json  Body JSON enviado
     * @return string|null app_shop_id quando encontrado
     */
    private function resolveAppShopIdFromRequest(array $query, array $json): ?string
    {
        $appShopId = data_get($query, 'app_shop_id');
        if (! is_string($appShopId) || trim($appShopId) === '') {
            $appShopId = data_get($json, 'app_shop_id');
        }

        if (! is_string($appShopId)) {
            return null;
        }

        $appShopId = trim($appShopId);

        return $appShopId !== '' ? $appShopId : null;
    }

    /**
     * Executa refresh + get do token de autorizacao da loja.
     *
     * @param  string  $appShopId  app_shop_id da loja
     * @return string auth_token renovado
     */
    private function refreshAuthorizationAndGetToken(string $appShopId): string
    {
        $query = array_merge($this->food99Credentials(), [
            'app_shop_id' => $appShopId,
        ]);

        $this->food99RequestInternal(
            method: 'GET',
            path: '/v1/auth/authtoken/refresh',
            query: $query,
            json: [],
            allowRefreshRetry: false,
        );

        $tokenPayload = $this->food99RequestInternal(
            method: 'GET',
            path: '/v1/auth/authtoken/get',
            query: $query,
            json: [],
            allowRefreshRetry: false,
        );

        $authToken = $this->extractFirstString(
            payload: $tokenPayload,
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
                msg: 'Nao foi possivel obter auth_token apos refresh automatico da autorizacao.',
                code: 422,
            );
        }

        return $authToken;
    }

    /**
     * Extrai a primeira string nao vazia de uma lista de caminhos no payload.
     *
     * @param  array<string, mixed>  $payload  Payload completo
     * @param  array<int, string>  $candidates  Lista de caminhos para tentativa
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
     * Retorna credenciais da aplicacao configuradas no ambiente.
     *
     * @return array<string, string> app_id e app_secret
     */
    protected function food99Credentials(): array
    {
        $appId = (string) config('services.food99.app_id');
        $appSecret = (string) config('services.food99.app_secret');

        if ($appId === '' || $appSecret === '') {
            throw new ApiException(
                msg: 'Configure FOOD99_APP_ID e FOOD99_APP_SECRET no ambiente.',
                code: 500,
            );
        }

        return [
            'app_id' => $appId,
            'app_secret' => $appSecret,
        ];
    }

    /**
     * Garante que a resposta HTTP da 99Food foi bem-sucedida (2xx).
     *
     * @param  Response  $httpResponse  Resposta HTTP da chamada externa
     */
    private function ensureFood99HttpSuccess(Response $httpResponse): void
    {
        if ($httpResponse->successful()) {
            return;
        }

        $payload = $httpResponse->json();
        $message = is_array($payload)
            ? (string) (data_get($payload, 'msg') ?? data_get($payload, 'message') ?? 'Erro ao consumir API da 99Food.')
            : 'Erro ao consumir API da 99Food.';

        throw new ApiException(
            msg: $message,
            code: $httpResponse->status() > 0 ? $httpResponse->status() : 502,
        );
    }
}
