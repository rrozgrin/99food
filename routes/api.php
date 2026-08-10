<?php

/**
 * Rotas da API — Prefixo: /api/v1
 *
 * Todas as rotas aqui definidas são automaticamente prefixadas com /api
 * pelo bootstrap/app.php. O grupo abaixo adiciona o prefixo /v1,
 * resultando no caminho final /api/v1/...
 *
 * Estrutura:
 *  - Rotas públicas (sem autenticação)
 *  - Rotas protegidas (middleware jwt.verify)
 *
 * Para adicionar um novo domínio de rotas, siga o padrão:
 *  1. Crie o grupo com prefix do domínio
 *  2. Aponte para o Controller do domínio
 *  3. Mantenha os controllers enxutos — a lógica fica nos Services
 *
 * @see ApiJwtMiddleware — Middleware de validação JWT
 * @see AuthController — Controller de autenticação
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Food99\Auth\Food99AuthController;
use App\Http\Controllers\Food99\Catalog\Food99CatalogController;
use App\Http\Controllers\Food99\Orders\Food99OrderSyncController;
use App\Http\Controllers\Food99\Webhook\Food99WebhookController;
use App\Http\Controllers\Food99\Webhook\Food99WebhookLogController;
use App\Http\Middleware\ApiJwtMiddleware;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1'], function (): void {

    /*
    |--------------------------------------------------------------------------
    | Rotas Públicas (sem autenticação)
    |--------------------------------------------------------------------------
    */

    // Autenticação — Login do usuário
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('api.v1.login');

    // Callback da 99Food (eventos de pedido)
    Route::post('/99food/webhook', [Food99WebhookController::class, 'receive'])
        ->name('api.v1.food99.webhook.receive');

    /*
    |--------------------------------------------------------------------------
    | Rotas Protegidas (requerem JWT válido)
    |--------------------------------------------------------------------------
    |
    | Todas as rotas dentro deste grupo passam pelo middleware jwt.verify,
    | que valida o token JWT no header Authorization: Bearer {token}.
    |
    */

    Route::group(['middleware' => ['jwt.verify']], function (): void {

        // Dados do usuário autenticado
        Route::get('/me', [AuthController::class, 'me'])
            ->name('api.v1.me');

        // Renovar token JWT
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->name('api.v1.refresh');

        // Integracao 99Food - autenticacao por loja
        Route::group(['prefix' => 'food99/auth'], function (): void {
            // Lista as lojas 99Food do cliente logado
            Route::get('/shops', [Food99AuthController::class, 'shops'])
                ->name('api.v1.food99.auth.shops');

            // Gera URL de autorizacao para vincular loja ao app
            Route::post('/authorization-url', [Food99AuthController::class, 'authorizationUrl'])
                ->name('api.v1.food99.auth.authorization-url');

            // Busca e persiste token de acesso da loja
            Route::post('/token/get', [Food99AuthController::class, 'getToken'])
                ->name('api.v1.food99.auth.token.get');

            // Forca refresh do token da loja
            Route::post('/token/refresh', [Food99AuthController::class, 'refreshToken'])
                ->name('api.v1.food99.auth.token.refresh');

            // Consulta token atualmente salvo localmente
            Route::get('/token/local/{appShopId}', [Food99AuthController::class, 'localToken'])
                ->name('api.v1.food99.auth.token.local');
        });

        // Integracao 99Food - catalogo
        Route::group(['prefix' => 'food99/catalog'], function (): void {
            // Cria/atualiza menu da loja no catalogo local
            Route::post('/menus/upsert', [Food99CatalogController::class, 'upsertMenu'])
                ->name('api.v1.food99.catalog.menus.upsert');

            // Lista menus da loja no catalogo local
            Route::get('/menus/{appShopId}', [Food99CatalogController::class, 'listMenus'])
                ->name('api.v1.food99.catalog.menus.list');

            // Cria/atualiza categoria da loja no catalogo local
            Route::post('/categories/upsert', [Food99CatalogController::class, 'upsertCategory'])
                ->name('api.v1.food99.catalog.categories.upsert');

            // Lista categorias da loja no catalogo local
            Route::get('/categories/{appShopId}', [Food99CatalogController::class, 'listCategories'])
                ->name('api.v1.food99.catalog.categories.list');

            // Cria/atualiza item da loja no catalogo local
            Route::post('/items/upsert', [Food99CatalogController::class, 'upsertItem'])
                ->name('api.v1.food99.catalog.items.upsert');

            // Configura item local ja criado pelo ERP para futura publicacao
            Route::post('/items/configure', [Food99CatalogController::class, 'configureItem'])
                ->name('api.v1.food99.catalog.items.configure');

            // Lista itens da loja no catalogo local
            Route::get('/items/{appShopId}', [Food99CatalogController::class, 'listItems'])
                ->name('api.v1.food99.catalog.items.list');

            // Substitui os itens vinculados a uma categoria
            Route::post('/categories/link-items', [Food99CatalogController::class, 'linkCategoryItems'])
                ->name('api.v1.food99.catalog.categories.link-items');

            // Monta preview do payload de publicacao para validacao
            Route::post('/payload/preview', [Food99CatalogController::class, 'previewPayload'])
                ->name('api.v1.food99.catalog.payload.preview');

            // Publica catalogo completo ou seletivo na 99Food
            Route::post('/publish', [Food99CatalogController::class, 'publishCatalog'])
                ->name('api.v1.food99.catalog.publish');

            // Sincroniza catalogo remoto da 99Food e preenche vinculos locais
            Route::post('/sync-remote', [Food99CatalogController::class, 'syncRemoteCatalog'])
                ->name('api.v1.food99.catalog.sync-remote');

            // Lista historico de jobs de publicacao da loja
            Route::get('/publish/jobs/{appShopId}', [Food99CatalogController::class, 'publishJobs'])
                ->name('api.v1.food99.catalog.publish.jobs');
        });

        // Integracao 99Food - pedidos
        Route::group(['prefix' => 'food99/orders'], function (): void {
            // Lista pedidos pendentes/falhos para operacao manual
            Route::get('/sync-queue', [Food99OrderSyncController::class, 'syncQueue'])
                ->name('api.v1.food99.orders.sync-queue');

            // Retorna detalhe de um pedido especifico
            Route::get('/{food99OrderId}', [Food99OrderSyncController::class, 'show'])
                ->name('api.v1.food99.orders.show');

            // Reprocessa manualmente a sincronizacao de um pedido com o ERP
            Route::post('/{food99OrderId}/sync-erp', [Food99OrderSyncController::class, 'reprocess'])
                ->name('api.v1.food99.orders.sync-erp');
        });

        // Integracao 99Food - logs de webhook
        Route::group(['prefix' => 'food99/webhooks'], function (): void {
            // Lista logs de webhooks recebidos
            Route::get('/logs', [Food99WebhookLogController::class, 'index'])
                ->name('api.v1.food99.webhooks.logs');
        });

        /*
        |----------------------------------------------------------------------
        | Exemplo: Grupo de domínio
        |----------------------------------------------------------------------
        |
        | Para adicionar rotas de um novo domínio (ex: Produtos, Pedidos),
        | crie um grupo com prefix e aponte para o Controller correspondente:
        |
        | Route::group(['prefix' => 'produtos'], function (): void {
        |     Route::get('/', [ProdutosController::class, 'index']);
        |     Route::post('/', [ProdutosController::class, 'store']);
        |     Route::get('/{id}', [ProdutosController::class, 'show']);
        |     Route::put('/{id}', [ProdutosController::class, 'update']);
        |     Route::delete('/{id}', [ProdutosController::class, 'destroy']);
        | });
        |
        */
    });
});
