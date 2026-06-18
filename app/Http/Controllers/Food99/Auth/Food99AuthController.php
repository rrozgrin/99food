<?php

declare(strict_types=1);

namespace App\Http\Controllers\Food99\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use App\Services\Food99\Auth\Food99AuthService;

/**
 * Controller de autenticacao da 99Food.
 *
 * Endpoints internos para o hub de integracao:
 * - Gerar URL de vinculacao de loja
 * - Obter token por loja
 * - Renovar token por loja
 * - Consultar token armazenado localmente
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class Food99AuthController extends Controller
{
    /**
     * Gera URL de vinculacao da loja na 99Food.
     *
     * @param Request           $request Requisicao com payload opcional para 99Food
     * @param Food99AuthService $service Servico de autenticacao 99Food
     *
     * @return JsonResponse Resposta padrao com URL de autorizacao
     */
    #[OA\Post(
        path: '/food99/auth/authorization-url',
        summary: 'Gerar URL de vinculacao de loja na 99Food',
        tags: ['99Food - Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'URL gerada com sucesso'),
            new OA\Response(response: 400, description: 'Erro de validacao'),
            new OA\Response(response: 500, description: 'Erro interno'),
        ],
    )]
    public function authorizationUrl(Request $request, Food99AuthService $service): JsonResponse
    {
        $request->validate([
            'app_shop_id' => ['nullable', 'string', 'max:255'],
            'shop_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'store_name' => ['nullable', 'string', 'max:255'],
        ]);

        $conteudo = $service->getAuthorizationUrl(payload: $request->all());

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Lista as lojas 99Food do cliente logado.
     *
     * @param Food99AuthService $service Servico de autenticacao 99Food
     *
     * @return JsonResponse Resposta padrao com lojas do usuario
     */
    public function shops(Food99AuthService $service): JsonResponse
    {
        $conteudo = $service->listOwnedShops();

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Obtem e persiste auth_token de uma loja na 99Food.
     *
     * @param Request           $request Requisicao contendo app_shop_id
     * @param Food99AuthService $service Servico de autenticacao 99Food
     *
     * @return JsonResponse Resposta padrao com token por loja
     */
    #[OA\Post(
        path: '/food99/auth/token/get',
        summary: 'Obter auth_token por loja na 99Food',
        tags: ['99Food - Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['app_shop_id'],
                properties: [
                    new OA\Property(
                        property: 'app_shop_id',
                        type: 'string',
                        example: 'shop_123',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token obtido com sucesso'),
            new OA\Response(response: 422, description: 'Erro de negocio externo'),
            new OA\Response(response: 500, description: 'Erro interno'),
        ],
    )]
    public function getToken(Request $request, Food99AuthService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
        ]);

        $conteudo = $service->getTokenByStore(appShopId: (string) $validated['app_shop_id']);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Renova e persiste auth_token de uma loja na 99Food.
     *
     * @param Request           $request Requisicao contendo app_shop_id
     * @param Food99AuthService $service Servico de autenticacao 99Food
     *
     * @return JsonResponse Resposta padrao com token renovado
     */
    #[OA\Post(
        path: '/food99/auth/token/refresh',
        summary: 'Renovar auth_token por loja na 99Food',
        tags: ['99Food - Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['app_shop_id'],
                properties: [
                    new OA\Property(
                        property: 'app_shop_id',
                        type: 'string',
                        example: 'shop_123',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token renovado com sucesso'),
            new OA\Response(response: 422, description: 'Erro de negocio externo'),
            new OA\Response(response: 500, description: 'Erro interno'),
        ],
    )]
    public function refreshToken(Request $request, Food99AuthService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
        ]);

        $conteudo = $service->refreshTokenByStore(appShopId: (string) $validated['app_shop_id']);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Retorna token armazenado localmente para uma loja.
     *
     * @param string            $appShopId app_shop_id da loja
     * @param Food99AuthService $service   Servico de autenticacao 99Food
     *
     * @return JsonResponse Resposta padrao com token local
     */
    #[OA\Get(
        path: '/food99/auth/token/local/{appShopId}',
        summary: 'Consultar token local por loja na 99Food',
        tags: ['99Food - Auth'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'appShopId',
                description: 'ID externo da loja na 99Food',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Token local retornado com sucesso'),
            new OA\Response(response: 404, description: 'Token nao encontrado'),
        ],
    )]
    public function localToken(string $appShopId, Food99AuthService $service): JsonResponse
    {
        $conteudo = $service->getStoredTokenByStore(appShopId: $appShopId);

        if ($conteudo === null) {
            return response()->json(
                data: $this->send(
                    conteudo: null,
                    code: 404,
                    msg: 'Token local nao encontrado para o app_shop_id informado.',
                ),
                status: 404,
            );
        }

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }
}
