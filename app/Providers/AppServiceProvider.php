<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Kernel;
use App\Services\Extensions\BindsRepositorios;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider principal da aplicação.
 *
 * Responsável por:
 *  - Registrar bindings de repositórios via BindsRepositorios
 *  - Configurar rate limiters por tipo de operação
 *  - Bootstrapping de serviços da aplicação
 *
 * A tradução dos comandos Artisan para pt-BR é feita pelo
 * Kernel customizado (App\Console\Kernel) que utiliza o
 * TranslatingCommandLoader como decorator do command loader.
 *
 * @see BindsRepositorios — Classe que centraliza os bindings de repositórios
 * @see Kernel — Kernel que traduz descrições dos comandos
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra serviços no container de injeção de dependência.
     *
     * Os bindings de Repository Interface → Implementação Eloquent
     * são centralizados na classe BindsRepositorios.
     */
    public function register(): void
    {
        new BindsRepositorios(app: $this->app);
    }

    /**
     * Bootstrap de serviços da aplicação.
     *
     * Configura rate limiters diferenciados por tipo de operação:
     *  - api-leitura   — consultas e listagens (100 req/min por usuário)
     *  - api-escrita   — criação, edição e exclusão (30 req/min por usuário)
     *  - api-relatorio — relatórios pesados (5 req/min por usuário)
     *
     * Uso nas rotas:
     *   Route::get('/produtos', [...])->middleware('throttle:api-leitura');
     *   Route::post('/vendas', [...])->middleware('throttle:api-escrita');
     *   Route::get('/relatorio/financeiro', [...])->middleware('throttle:api-relatorio');
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                $request->ip().'|'.mb_strtolower((string) $request->input('login')),
            );
        });

        $this->configureRateLimiting();
    }

    /**
     * Registra os rate limiters da API.
     */
    private function configureRateLimiting(): void
    {
        // Consultas e listagens: 100 req/min por usuário autenticado, 30 por IP anônimo
        RateLimiter::for('api-leitura', function (Request $request): Limit {
            return $request->user()
                ? Limit::perMinute(100)->by($request->user()->getKey())
                : Limit::perMinute(30)->by($request->ip());
        });

        // Criação, edição e exclusão: 30 req/min por usuário, 10 por IP anônimo
        RateLimiter::for('api-escrita', function (Request $request): Limit {
            return $request->user()
                ? Limit::perMinute(30)->by($request->user()->getKey())
                : Limit::perMinute(10)->by($request->ip());
        });

        // Relatórios pesados: 5 req/min por usuário (bloqueado para anônimos)
        RateLimiter::for('api-relatorio', function (Request $request): Limit {
            return $request->user()
                ? Limit::perMinute(5)->by($request->user()->getKey())
                : Limit::none();
        });
    }
}
