<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\ApiException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use OpenApi\Attributes as OA;

/**
 * Controller de autenticação via JWT.
 *
 * Gerencia o fluxo de login, obtenção de dados do usuário autenticado
 * e renovação de tokens JWT.
 *
 * Endpoints:
 *  - POST   /api/v1/login   — Autentica o usuário e retorna o token JWT
 *  - GET    /api/v1/me      — Retorna os dados do usuário autenticado
 *  - POST   /api/v1/refresh — Renova o token JWT antes de expirar
 *
 * NOTA IMPORTANTE: A base legada pode conter senhas em texto plano.
 * O método attempt() faz validação híbrida:
 * - usa Hash::check() para registros já migrados
 * - faz upgrade automático para hash quando encontra senha legada válida
 *
 * @see \App\Http\Middleware\ApiJwtMiddleware — Middleware que valida JWT nas rotas protegidas
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class AuthController extends Controller
{
    /**
     * Autentica o usuário e retorna o token JWT.
     *
     * @param Request $request Requisição com 'login' e 'senha'
     *
     * @return JsonResponse Token JWT ou erro 401
     */
    #[OA\Post(
        path: '/login',
        summary: 'Autenticar usuário',
        description: 'Realiza login com credenciais e retorna um token JWT válido.',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Credenciais de acesso do usuário',
            content: new OA\JsonContent(
                required: ['login', 'senha'],
                properties: [
                    new OA\Property(
                        property: 'login',
                        type: 'string',
                        example: 'admin',
                        description: 'Login do usuário',
                    ),
                    new OA\Property(
                        property: 'senha',
                        type: 'string',
                        example: '123456',
                        description: 'Senha do usuário',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login realizado com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/TokenJWT'),
            ),
            new OA\Response(
                response: 401,
                description: 'Credenciais inválidas',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaErro'),
            ),
        ],
    )]
    public function login(Request $request): JsonResponse
    {
        $credenciais = $request->only('login', 'senha');
        $token = $this->attempt(credentials: $credenciais);

        if ($token) {
            return $this->respondWithToken(token: $token);
        }

        return response()->json(
            data: $this->send(
                conteudo: '',
                code: 401,
                msg: 'Login inválido',
            ),
            status: 401,
        );
    }

    /**
     * Retorna os dados do usuário autenticado.
     *
     * Requer token JWT válido no header Authorization.
     *
     * @return JsonResponse Dados do usuário no padrão ResponseApi
     */
    #[OA\Get(
        path: '/me',
        summary: 'Dados do usuário autenticado',
        description: 'Retorna os dados do usuário logado com base no token JWT informado.',
        tags: ['Autenticação'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dados do usuário retornados com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaPadrao'),
            ),
            new OA\Response(
                response: 401,
                description: 'Token inválido ou expirado',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaErro'),
            ),
        ],
    )]
    public function me(): JsonResponse
    {
        return response()->json(
            data: $this->send(conteudo: $this->guard()->user()),
        );
    }

    /**
     * Renova o token JWT do usuário autenticado.
     *
     * O token atual é invalidado e um novo é gerado.
     * Deve ser chamado antes do token expirar.
     *
     * @return JsonResponse Novo token JWT
     *
     * @throws ApiException Se não for possível renovar o token
     */
    #[OA\Post(
        path: '/refresh',
        summary: 'Renovar token JWT',
        description: 'Invalida o token atual e gera um novo. Deve ser chamado antes do token expirar.',
        tags: ['Autenticação'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token renovado com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/TokenJWT'),
            ),
            new OA\Response(
                response: 400,
                description: 'Erro ao renovar token',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaErro'),
            ),
        ],
    )]
    public function refresh(): JsonResponse
    {
        try {
            return $this->respondWithToken(token: $this->guard()->refresh());
        } catch (JWTException) {
            throw new ApiException(
                msg: 'Erro ao atualizar token de acesso.',
                code: 400,
            );
        }
    }

    /**
     * Tenta autenticar o usuário com as credenciais informadas.
     *
     * Estratégia de compatibilidade:
     *  - Se a senha já estiver hasheada, valida com Hash::check().
     *  - Se estiver em texto plano legado e bater, faz upgrade automático para hash.
     *
     * @param array{login: string, senha: string} $credentials Credenciais do usuário
     * @param bool                                 $login       Se true, gera o token JWT
     *
     * @return string|bool Token JWT (se login=true) ou bool
     *
     * @throws ApiException Se credenciais não forem informadas
     */
    private function attempt(array $credentials = [], bool $login = true): string|bool
    {
        if (empty($credentials)) {
            throw new ApiException(
                msg: 'Credenciais não informadas.',
                code: 401,
            );
        }

        $user = User::where('login', $credentials['login'])
            ->where('ativo', 'A')
            ->first();

        if (! $user) {
            return false;
        }

        $rawPassword = (string) $credentials['senha'];
        $storedPassword = (string) ($user->senha ?? '');

        if ($storedPassword === '') {
            return false;
        }

        $passwordInfo = password_get_info($storedPassword);
        $isStoredHashed = $passwordInfo['algo'] !== null;

        if ($isStoredHashed && ! Hash::check($rawPassword, $storedPassword)) {
            return false;
        }

        if (! $isStoredHashed) {
            if (! hash_equals($storedPassword, $rawPassword)) {
                return false;
            }

            $user->senha = Hash::make($rawPassword);
            $user->save();
        }

        return $login ? $this->guard()->login($user) : true;
    }

    /**
     * Monta a resposta padrão com o token JWT.
     *
     * @param string $token Token JWT gerado
     *
     * @return JsonResponse Resposta com access_token, token_type e expires_in
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $this->guard()->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Obtém o guard de autenticação da API (JWT).
     *
     * @return \Illuminate\Contracts\Auth\Guard|\PHPOpenSourceSaver\JWTAuth\JWTGuard
     */
    protected function guard(): \Illuminate\Contracts\Auth\Guard
    {
        return Auth::guard('api');
    }
}
