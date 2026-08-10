<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Traits\SendResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de verificação de permissões RBAC.
 *
 * Verifica se o usuário autenticado possui a permissão necessária
 * para acessar o endpoint. Deve ser usado APÓS o middleware jwt.verify.
 *
 * Registro em bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias([
 *           'permission' => \App\Http\Middleware\CheckPermissionMiddleware::class,
 *       ]);
 *   })
 *
 * Uso nas rotas:
 *   Route::get('/relatorio/financeiro', [...])
 *       ->middleware(['jwt.verify', 'permission:relatorio.financeiro']);
 *
 *   Route::post('/vendas', [...])
 *       ->middleware(['jwt.verify', 'permission:vendas.criar']);
 */
class CheckPermissionMiddleware
{
    use SendResponse;

    /**
     * Verifica se o usuário possui a permissão exigida pelo endpoint.
     *
     * @param  string  $permission  Permissão no formato 'modulo.acao'.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var User|null $user */
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(
                $this->send(code: Response::HTTP_UNAUTHORIZED, msg: 'Não autenticado.'),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if (! $user->hasPermission($permission)) {
            return response()->json(
                $this->send(
                    code: Response::HTTP_FORBIDDEN,
                    msg: "Acesso negado. Permissão necessária: [{$permission}]",
                ),
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
