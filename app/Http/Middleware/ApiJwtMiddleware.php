<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Traits\SendResponse;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;

/**
 * Middleware de validação de token JWT para rotas protegidas da API.
 *
 * Verifica se o header Authorization contém um Bearer token JWT válido.
 * Em caso de falha, retorna uma resposta JSON padronizada com o erro específico.
 *
 * Erros tratados:
 *  - Token inválido (malformado ou assinatura incorreta)
 *  - Token expirado (TTL ultrapassado)
 *  - Token ausente (header Authorization não enviado)
 *
 * Uso nas rotas:
 *     Route::group(['middleware' => ['jwt.verify']], function () {
 *         // Rotas protegidas
 *     });
 *
 * O alias 'jwt.verify' é registrado em bootstrap/app.php.
 *
 * @see \App\Services\Traits\SendResponse — Trait de resposta padronizada
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ApiJwtMiddleware
{
    use SendResponse;

    /**
     * Valida o token JWT da requisição.
     *
     * @param Request $request Requisição HTTP
     * @param Closure $next    Próximo middleware na pipeline
     *
     * @return Response Resposta HTTP (sucesso ou erro JSON)
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            $response = match (true) {
                $e instanceof TokenInvalidException => $this->send(
                    conteudo: 'token-error',
                    code: Response::HTTP_UNAUTHORIZED,
                    msg: 'Token inválido. Atualize sua página ou faça login novamente no sistema.',
                ),

                $e instanceof TokenExpiredException => $this->send(
                    conteudo: 'token-expirado',
                    code: Response::HTTP_UNAUTHORIZED,
                    msg: 'Token expirado. Atualize sua página ou faça login novamente no sistema.',
                ),

                default => $this->send(
                    conteudo: 'token-error',
                    code: Response::HTTP_UNAUTHORIZED,
                    msg: 'Token de autorização não encontrado.',
                ),
            };

            return response()->json($response, Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
