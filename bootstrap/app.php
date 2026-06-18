<?php

/**
 * Polyfill de mb_split para ambientes sem extensao mbstring.
 *
 * O Laravel usa mb_split internamente em Str::studly().
 * Em servidores onde mbstring nao esta habilitado, esta funcao
 * evita fatal error mantendo comportamento equivalente com preg_split.
 *
 * @param string      $pattern  Padrao regex sem delimitador
 * @param string      $string   Texto de entrada
 * @param int         $limit    Limite de partes (padrao -1)
 * @param string|null $encoding Encoding (ignorado no fallback)
 *
 * @return array<int, string>|false Partes do texto ou false em erro de regex
 */
if (! function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1, ?string $encoding = null): array|false
    {
        unset($encoding);

        $delimiter = '/';
        $escapedPattern = str_replace($delimiter, '\\' . $delimiter, $pattern);

        return preg_split($delimiter . $escapedPattern . $delimiter . 'u', $string, $limit);
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Services\ResponseApi\ResponseApi;
use App\Services\ResponseApi\ResponseApiDev;
use App\Exceptions\ApiException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Registra o Kernel de Console customizado que traduz
 * as descrições dos comandos Artisan para pt-BR.
 *
 * O binding é feito APÓS create() para sobrescrever o
 * Kernel padrão registrado pelo ApplicationBuilder.
 *
 * @see \App\Console\Kernel
 * @see \App\Console\TranslatingCommandLoader
 */
$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.verify'  => \App\Http\Middleware\ApiJwtMiddleware::class,
            'permission'  => \App\Http\Middleware\CheckPermissionMiddleware::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Renderiza todas as exceções da API como JSON padronizado.
         *
         * Em produção: retorna ResponseApi (sem detalhes internos).
         * Em desenvolvimento: retorna ResponseApiDev (com file, line, trace).
         */
        $exceptions->renderable(function (Throwable $exception) {
            $isProduction = config('app.env') === 'production';

            if ($exception instanceof ValidationException) {
                $response = $isProduction
                    ? new ResponseApi(
                        conteudo: $exception->errors(),
                        msg: 'Dados de entrada inválidos.',
                        code: 422,
                    )
                    : new ResponseApiDev(
                        conteudo: $exception->errors(),
                        msg: 'Dados de entrada inválidos.',
                        code: 422,
                    );

                return response()->json($response, 422);
            }

            if ($isProduction) {
                $response = match (true) {
                    $exception instanceof ApiException
                        => new ResponseApi(
                            conteudo: null,
                            msg: $exception->getMessage(),
                            code: $exception->getCode() ?: 400,
                        ),

                    $exception instanceof MethodNotAllowedHttpException
                        => new ResponseApi(
                            conteudo: null,
                            msg: 'Verbo HTTP inválido. Utilize apenas GET, POST, PUT ou DELETE.',
                            code: 405,
                        ),

                    $exception instanceof ModelNotFoundException
                        => new ResponseApi(
                            conteudo: null,
                            msg: 'Item não encontrado.',
                            code: 404,
                        ),

                    $exception instanceof NotFoundHttpException
                        => new ResponseApi(
                            conteudo: null,
                            msg: 'Rota não encontrada.',
                            code: 404,
                        ),

                    default => new ResponseApi(
                        conteudo: null,
                        msg: 'Ocorreu um erro inesperado. Nossa equipe já foi notificada.',
                        code: 500,
                    ),
                };
            } else {
                $response = new ResponseApiDev(throwable: $exception);
            }

            $httpCode = match (true) {
                is_int($response->getCode()) && $response->getCode() >= 100 && $response->getCode() < 600
                    => $response->getCode(),
                default => 500,
            };

            return response()->json($response, $httpCode);
        });
    })->create();

$app->singleton(
    \Illuminate\Contracts\Console\Kernel::class,
    \App\Console\Kernel::class,
);

return $app;
