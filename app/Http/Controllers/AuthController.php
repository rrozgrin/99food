<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Middleware\ApiJwtMiddleware;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

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
 * As senhas do ERP legado são armazenadas como hashes e verificadas pelo
 * hasher configurado na aplicação.
 *
 * @see ApiJwtMiddleware — Middleware que valida JWT nas rotas protegidas
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class AuthController extends Controller
{
    /**
     * Autentica o usuário e retorna o token JWT.
     *
     * @param  LoginRequest  $request  Requisição com 'login' e 'senha'
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
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Login inválido'),
                    ],
                ),
            ),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $credenciais = $request->validated();
        $token = $this->attempt(credentials: $credenciais);

        if ($token) {
            return $this->respondWithToken(token: $token);
        }

        return response()->json(
            data: ['error' => 'Login inválido'],
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
     * @param  array{login: string, senha: string}  $credentials  Credenciais do usuário
     * @param  bool  $login  Se true, gera o token JWT
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

        if ($user && $this->passwordMatches($credentials['senha'], $user->senha)) {
            return $login ? $this->guard()->login($user) : true;
        }

        return false;
    }

    /**
     * Monta a resposta padrão com o token JWT.
     *
     * @param  string  $token  Token JWT gerado
     * @return JsonResponse Resposta com access_token, token_type e expires_in
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Verifica apenas hashes reconhecidos pelo driver de hashing configurado.
     *
     * O ERP deve armazenar bcrypt ou Argon2 em uma coluna que comporte o hash.
     */
    private function passwordMatches(string $password, ?string $hash): bool
    {
        if (! is_string($hash) || password_get_info($hash)['algo'] === null) {
            Log::warning('Tentativa de login com hash de senha ERP incompatível.', [
                'login' => request()->input('login'),
            ]);

            return false;
        }

        try {
            return Hash::check($password, $hash);
        } catch (\RuntimeException) {
            Log::warning('Tentativa de login com hash de senha ERP não suportado pelo hasher configurado.', [
                'login' => request()->input('login'),
            ]);

            return false;
        }
    }

    /**
     * Obtém o guard de autenticação da API (JWT).
     *
     * @return Guard|JWTGuard
     */
    protected function guard(): Guard
    {
        return Auth::guard('api');
    }
}
