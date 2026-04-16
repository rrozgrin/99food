<?php

declare(strict_types=1);

namespace App\Http\Controllers\Food99\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use App\Services\Food99\Catalog\Food99CatalogManagementService;
use App\Services\Food99\Catalog\Food99CatalogPayloadService;
use App\Services\Food99\Catalog\Food99CatalogPublishService;

/**
 * Controller de catalogo da 99Food.
 *
 * Disponibiliza endpoint de preview para validar montagem do payload
 * antes de enviar para a API externa.
 */
class Food99CatalogController extends Controller
{
    /**
     * Cadastra ou atualiza um menu por loja.
     */
    public function upsertMenu(Request $request, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
            'app_menu_id' => ['required', 'string', 'max:100'],
            'menu_name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $conteudo = $service->upsertMenu($validated);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Lista menus por loja.
     */
    public function listMenus(Request $request, string $appShopId, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:local,published'],
        ]);

        $conteudo = $service->listMenus(
            appShopId: $appShopId,
            view: (string) ($validated['view'] ?? 'local'),
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Cadastra ou atualiza uma categoria por loja.
     */
    public function upsertCategory(Request $request, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
            'app_menu_id' => ['required', 'string', 'max:100'],
            'app_category_id' => ['required', 'string', 'max:100'],
            'category_name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $conteudo = $service->upsertCategory($validated);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Lista categorias por loja.
     */
    public function listCategories(Request $request, string $appShopId, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:local,published'],
        ]);

        $conteudo = $service->listCategories(
            appShopId: $appShopId,
            view: (string) ($validated['view'] ?? 'local'),
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Cadastra ou atualiza um item por loja.
     */
    public function upsertItem(Request $request, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
            'app_category_id' => ['required', 'string', 'max:100'],
            'app_item_id' => ['nullable', 'string', 'max:120'],
            'id_cadastro' => ['nullable', 'integer', 'min:1'],
            'id_produto' => ['required', 'integer', 'min:1'],
            'id_grade' => ['nullable', 'integer', 'min:1'],
            'app_external_id' => ['nullable', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:50'],
            'short_desc' => ['nullable', 'string', 'max:300'],
            'head_img' => ['nullable', 'string', 'max:300'],
            'price_source' => ['nullable', 'in:produto,grade,override'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'required_without:price_amount'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'required_without:price_cents'],
            'tax_rate' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'publish_status' => ['nullable', 'in:draft,queued,published,failed'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'payload_snapshot' => ['nullable', 'array'],
        ]);

        $conteudo = $service->upsertItem($validated);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Lista itens por loja.
     */
    public function listItems(Request $request, string $appShopId, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:local,published'],
        ]);

        $conteudo = $service->listItems(
            appShopId: $appShopId,
            view: (string) ($validated['view'] ?? 'local'),
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Sincroniza catalogo remoto da 99Food e preenche vinculos locais.
     */
    public function syncRemoteCatalog(Request $request, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
        ]);

        $conteudo = $service->syncRemoteCatalog((string) $validated['app_shop_id']);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Vincula itens em uma categoria, substituindo o vinculo atual.
     */
    public function linkCategoryItems(Request $request, Food99CatalogManagementService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
            'app_category_id' => ['required', 'string', 'max:100'],
            'app_item_ids' => ['required', 'array', 'min:1'],
            'app_item_ids.*' => ['required', 'string', 'max:120'],
        ]);

        $conteudo = $service->linkCategoryItems($validated);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Monta e retorna o payload de upload de menu da 99Food.
     *
     * @param Request                   $request Requisicao com app_shop_id
     * @param Food99CatalogPayloadService $service Servico de montagem de payload
     *
     * @return JsonResponse Payload montado no padrao ResponseApi
     */
    #[OA\Post(
        path: '/food99/catalog/payload/preview',
        summary: 'Preview do payload de publicacao de itens na 99Food',
        tags: ['99Food - Catalog'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['app_shop_id'],
                properties: [
                    new OA\Property(
                        property: 'app_shop_id',
                        type: 'string',
                        example: 'wc-sandbox-002',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Payload montado com sucesso'),
            new OA\Response(response: 404, description: 'Loja nao encontrada'),
            new OA\Response(response: 422, description: 'Inconsistencia na estrutura de catalogo'),
            new OA\Response(response: 500, description: 'Erro interno'),
        ],
    )]
    public function previewPayload(Request $request, Food99CatalogPayloadService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
        ]);

        $conteudo = $service->buildUploadPayloadPreview(
            appShopId: (string) $validated['app_shop_id'],
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Publica o catalogo da loja na API da 99Food.
     *
     * @param Request                      $request Requisicao com app_shop_id e, opcionalmente, app_item_ids
     * @param Food99CatalogPublishService   $service Servico de publicacao de catalogo
     *
     * @return JsonResponse Resultado da publicacao
     */
    #[OA\Post(
        path: '/food99/catalog/publish',
        summary: 'Publica catalogo da loja na 99Food (com filtro opcional por item)',
        tags: ['99Food - Catalog'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['app_shop_id'],
                properties: [
                    new OA\Property(
                        property: 'app_shop_id',
                        type: 'string',
                        example: 'wc-sandbox-002',
                    ),
                    new OA\Property(
                        property: 'app_item_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['item-123', 'item-456'],
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Catalogo publicado com sucesso'),
            new OA\Response(response: 404, description: 'Loja ou catalogo nao encontrado'),
            new OA\Response(response: 422, description: 'Token ausente ou catalogo invalido'),
            new OA\Response(response: 502, description: 'Falha na comunicacao com a 99Food'),
        ],
    )]
    public function publishCatalog(Request $request, Food99CatalogPublishService $service): JsonResponse
    {
        $validated = $request->validate([
            'app_shop_id' => ['required', 'string', 'max:255'],
            'app_item_ids' => ['nullable', 'array', 'min:1'],
            'app_item_ids.*' => ['required', 'string', 'max:120'],
        ]);

        $userId = auth('api')->id();
        $appItemIds = (array) ($validated['app_item_ids'] ?? []);

        $conteudo = $service->publishCatalog(
            (string) $validated['app_shop_id'],
            is_numeric($userId) ? (int) $userId : null,
            $appItemIds,
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    /**
     * Lista historico de jobs de publicacao de uma loja.
     *
     * @param string                     $appShopId ID da loja
     * @param Food99CatalogPublishService $service   Servico de publicacao
     *
     * @return JsonResponse Historico de jobs
     */
    public function publishJobs(string $appShopId, Food99CatalogPublishService $service): JsonResponse
    {
        $conteudo = $service->listJobs(appShopId: $appShopId);

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }
}
