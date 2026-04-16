<?php

declare(strict_types=1);

namespace App\Services\Food99\Catalog;

use App\Exceptions\ApiException;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Traits\WithTransaction;
use App\Repository\Contracts\Models\BaseErp\GradeRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\ProdutoRepositoryInterface;
use App\Services\Food99\Traits\InteractsWithFood99Api;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopMenuRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryItemRepositoryInterface;

/**
 * Service de manipulacao de catalogo local da 99Food.
 *
 * Permite cadastrar/atualizar menu, categoria, item e vinculos
 * usados na montagem de payload para publicacao.
 */
class Food99CatalogManagementService
{
    use InteractsWithFood99Api;
    use WithTransaction;

    public function __construct(
        private readonly UsuarioLogadoService $usuarioLogado,
        private readonly Food99ShopRepositoryInterface $shopRepository,
        private readonly Food99StoreTokenRepositoryInterface $storeTokenRepository,
        private readonly Food99ShopMenuRepositoryInterface $shopMenuRepository,
        private readonly Food99ShopCategoryRepositoryInterface $shopCategoryRepository,
        private readonly Food99ShopItemRepositoryInterface $shopItemRepository,
        private readonly Food99ShopCategoryItemRepositoryInterface $shopCategoryItemRepository,
        private readonly ProdutoRepositoryInterface $produtoRepository,
        private readonly GradeRepositoryInterface $gradeRepository,
    ) {}

    /**
     * Cadastra ou atualiza um menu da loja.
     *
     * @param array<string, mixed> $input Dados do menu
     *
     * @return array<string, mixed> Menu salvo
     */
    public function upsertMenu(array $input): array
    {
        $shop = $this->resolveShopByAppShopId((string) $input['app_shop_id']);

        $menu = $this->shopMenuRepository->updateOrCreate(
            attributes: [
                'food99_shop_id' => (int) $shop->id,
                'app_menu_id' => (string) $input['app_menu_id'],
            ],
            values: [
                'menu_name' => (string) $input['menu_name'],
                'sort_order' => (int) ($input['sort_order'] ?? 0),
                'is_active' => (bool) ($input['is_active'] ?? true),
                'metadata' => $input['metadata'] ?? null,
            ],
        );

        return $menu->toArray();
    }

    /**
     * Lista menus da loja por app_shop_id.
     *
     * @param string $appShopId app_shop_id da loja
     * @param string $view      Visao desejada: local|published
     *
     * @return array<string, mixed> Menus encontrados
     */
    public function listMenus(string $appShopId, string $view = 'local'): array
    {
        $shop = $this->resolveShopByAppShopId($appShopId);

        if ($view === 'published') {
            $remoteCatalog = $this->fetchPublishedCatalogFromFood99(
                appShopId: $appShopId,
                food99ShopId: (int) $shop->id,
            );

            return [
                'app_shop_id' => $appShopId,
                'food99_shop_id' => (int) $shop->id,
                'view' => 'published',
                'source' => 'food99_api',
                'menus' => (array) ($remoteCatalog['menus'] ?? []),
            ];
        }

        $menus = $this->shopMenuRepository->findByShopId((int) $shop->id)?->toArray() ?? [];

        return [
            'app_shop_id' => $appShopId,
            'food99_shop_id' => (int) $shop->id,
            'view' => 'local',
            'menus' => $menus,
        ];
    }

    /**
     * Cadastra ou atualiza uma categoria da loja.
     *
     * @param array<string, mixed> $input Dados da categoria
     *
     * @return array<string, mixed> Categoria salva
     */
    public function upsertCategory(array $input): array
    {
        $shop = $this->resolveShopByAppShopId((string) $input['app_shop_id']);
        $menu = $this->resolveMenuByAppMenuId(
            food99ShopId: (int) $shop->id,
            appMenuId: (string) $input['app_menu_id'],
        );

        $category = $this->shopCategoryRepository->updateOrCreate(
            attributes: [
                'food99_shop_id' => (int) $shop->id,
                'app_category_id' => (string) $input['app_category_id'],
            ],
            values: [
                'food99_shop_menu_id' => (int) $menu->id,
                'category_name' => (string) $input['category_name'],
                'sort_order' => (int) ($input['sort_order'] ?? 0),
                'is_active' => (bool) ($input['is_active'] ?? true),
                'metadata' => $input['metadata'] ?? null,
            ],
        );

        return $category->toArray();
    }

    /**
     * Lista categorias da loja por app_shop_id.
     *
     * @param string $appShopId app_shop_id da loja
     * @param string $view      Visao desejada: local|published
     *
     * @return array<string, mixed> Categorias encontradas
     */
    public function listCategories(string $appShopId, string $view = 'local'): array
    {
        $shop = $this->resolveShopByAppShopId($appShopId);

        if ($view === 'published') {
            $remoteCatalog = $this->fetchPublishedCatalogFromFood99(
                appShopId: $appShopId,
                food99ShopId: (int) $shop->id,
            );

            return [
                'app_shop_id' => $appShopId,
                'food99_shop_id' => (int) $shop->id,
                'view' => 'published',
                'source' => 'food99_api',
                'categories' => (array) ($remoteCatalog['categories'] ?? []),
            ];
        }

        $categories = $this->shopCategoryRepository->findByShopId((int) $shop->id)?->toArray() ?? [];

        return [
            'app_shop_id' => $appShopId,
            'food99_shop_id' => (int) $shop->id,
            'view' => 'local',
            'categories' => $categories,
        ];
    }

    /**
     * Cadastra ou atualiza um item da loja.
     *
     * @param array<string, mixed> $input Dados do item
     *
     * @return array<string, mixed> Item salvo
     */
    public function upsertItem(array $input): array
    {
        $shop = $this->resolveShopByAppShopId((string) $input['app_shop_id']);
        $category = $this->resolveCategoryByAppCategoryId(
            food99ShopId: (int) $shop->id,
            appCategoryId: (string) $input['app_category_id'],
        );
        $idProduto = (int) $input['id_produto'];
        $idGrade = isset($input['id_grade']) && $input['id_grade'] !== null
            ? (int) $input['id_grade']
            : null;

        [$priceAmount, $priceCents] = $this->resolvePriceValues($input);
        $appItemId = $this->resolveUpsertAppItemId(
            food99ShopId: (int) $shop->id,
            idProduto: $idProduto,
            idGrade: $idGrade,
            requestedAppItemId: $input['app_item_id'] ?? null,
        );

        $item = $this->shopItemRepository->updateOrCreate(
            attributes: [
                'food99_shop_id' => (int) $shop->id,
                'app_item_id' => $appItemId,
            ],
            values: [
                'food99_shop_category_id' => (int) $category->id,
                'id_cadastro' => isset($input['id_cadastro']) ? (int) $input['id_cadastro'] : null,
                'id_produto' => $idProduto,
                'id_grade' => $idGrade,
                'app_external_id' => $input['app_external_id'] ?? null,
                'item_name' => (string) $input['item_name'],
                'short_desc' => $input['short_desc'] ?? null,
                'head_img' => $input['head_img'] ?? null,
                'price_source' => (string) ($input['price_source'] ?? 'grade'),
                'price_amount' => $priceAmount,
                'price_cents' => $priceCents,
                'tax_rate' => isset($input['tax_rate']) && $input['tax_rate'] !== null
                    ? (int) $input['tax_rate']
                    : null,
                'is_active' => (bool) ($input['is_active'] ?? true),
                'publish_status' => (string) ($input['publish_status'] ?? 'draft'),
                'payload_snapshot' => $input['payload_snapshot'] ?? null,
            ],
        );

        // Mantem vinculo categoria-item para montagem do payload.
        $this->shopCategoryItemRepository->updateOrCreate(
            attributes: [
                'food99_shop_category_id' => (int) $category->id,
                'food99_shop_item_id' => (int) $item->id,
            ],
            values: [
                'sort_order' => (int) ($input['sort_order'] ?? 0),
            ],
        );

        $result = $item->toArray();
        $result['app_item_id'] = $appItemId;

        return $result;
    }

    /**
     * Lista itens da loja por app_shop_id.
     *
     * @param string $appShopId app_shop_id da loja
     * @param string $view      Visao desejada: local|published
     *
     * @return array<string, mixed> Itens encontrados
     */
    public function listItems(string $appShopId, string $view = 'local'): array
    {
        $shop = $this->resolveShopByAppShopId($appShopId);

        if ($view === 'published') {
            $remoteCatalog = $this->fetchPublishedCatalogFromFood99(
                appShopId: $appShopId,
                food99ShopId: (int) $shop->id,
            );

            return [
                'app_shop_id' => $appShopId,
                'food99_shop_id' => (int) $shop->id,
                'view' => 'published',
                'source' => 'food99_api',
                'items' => (array) ($remoteCatalog['items'] ?? []),
            ];
        }

        $items = $this->shopItemRepository->findByShopId((int) $shop->id)?->toArray() ?? [];

        return [
            'app_shop_id' => $appShopId,
            'food99_shop_id' => (int) $shop->id,
            'view' => 'local',
            'items' => $items,
        ];
    }

    /**
     * Sincroniza menus, categorias e itens do catalogo remoto da 99Food.
     *
     * @param string $appShopId app_shop_id da loja
     *
     * @return array<string, mixed>
     */
    public function syncRemoteCatalog(string $appShopId): array
    {
        $shop = $this->resolveShopByAppShopId($appShopId);
        $food99ShopId = (int) $shop->id;
        $idCadastro = (int) data_get($shop, 'id_cadastro');
        $erpUserId = $this->resolveAuthenticatedIdUsuario();

        $remoteCatalog = $this->fetchPublishedCatalogFromFood99(
            appShopId: $appShopId,
            food99ShopId: $food99ShopId,
        );
        $remoteMenus = (array) ($remoteCatalog['menus'] ?? []);
        $remoteCategories = (array) ($remoteCatalog['categories'] ?? []);
        $remoteItems = (array) ($remoteCatalog['items'] ?? []);

        $menuMap = [];
        $menuCount = 0;
        foreach ($remoteMenus as $menuPayload) {
            if (! is_array($menuPayload)) {
                continue;
            }

            $appMenuId = trim((string) data_get($menuPayload, 'app_menu_id'));
            $menuName = trim((string) data_get($menuPayload, 'menu_name'));
            if ($appMenuId === '' || $menuName === '') {
                continue;
            }

            $menu = $this->shopMenuRepository->updateOrCreate(
                attributes: [
                    'food99_shop_id' => $food99ShopId,
                    'app_menu_id' => $appMenuId,
                ],
                values: [
                    'menu_name' => mb_substr($menuName, 0, 100),
                    'sort_order' => is_numeric(data_get($menuPayload, 'sort_order')) ? (int) data_get($menuPayload, 'sort_order') : 0,
                    'is_active' => (bool) data_get($menuPayload, 'is_active', true),
                    'metadata' => $menuPayload,
                ],
            );

            $menuMap[$appMenuId] = (int) data_get($menu, 'id');
            $menuCount++;
        }

        $categoryMap = [];
        $categoryCount = 0;
        foreach ($remoteCategories as $categoryPayload) {
            if (! is_array($categoryPayload)) {
                continue;
            }

            $appCategoryId = trim((string) data_get($categoryPayload, 'app_category_id'));
            $categoryName = trim((string) data_get($categoryPayload, 'category_name'));
            if ($appCategoryId === '' || $categoryName === '') {
                continue;
            }

            $appMenuId = trim((string) data_get($categoryPayload, 'app_menu_id'));
            $menuId = $appMenuId !== '' && isset($menuMap[$appMenuId])
                ? $menuMap[$appMenuId]
                : (int) data_get(
                    $this->shopMenuRepository->findActiveByShopId($food99ShopId)?->first(),
                    'id',
                );

            $category = $this->shopCategoryRepository->updateOrCreate(
                attributes: [
                    'food99_shop_id' => $food99ShopId,
                    'app_category_id' => $appCategoryId,
                ],
                values: [
                    'food99_shop_menu_id' => $menuId > 0 ? $menuId : null,
                    'category_name' => mb_substr($categoryName, 0, 100),
                    'sort_order' => is_numeric(data_get($categoryPayload, 'sort_order')) ? (int) data_get($categoryPayload, 'sort_order') : 0,
                    'is_active' => (bool) data_get($categoryPayload, 'is_active', true),
                    'metadata' => $categoryPayload,
                ],
            );

            $categoryMap[$appCategoryId] = (int) data_get($category, 'id');
            $categoryCount++;
        }

        $itemCount = 0;
        $mappingCreated = 0;
        $mappingUpdated = 0;
        foreach ($remoteItems as $itemPayload) {
            if (! is_array($itemPayload)) {
                continue;
            }

            $appItemId = trim((string) data_get($itemPayload, 'app_item_id'));
            $itemName = trim((string) data_get($itemPayload, 'item_name'));
            if ($appItemId === '' || $itemName === '') {
                continue;
            }

            $appCategoryId = trim((string) data_get($itemPayload, 'app_category_id'));
        $shopCategoryId = $appCategoryId !== '' && isset($categoryMap[$appCategoryId])
                ? $categoryMap[$appCategoryId]
                : $this->shopCategoryRepository->findIdByShopAndAppCategoryId(
                    food99ShopId: $food99ShopId,
                    appCategoryId: $appCategoryId,
                );

            $existingItem = $this->shopItemRepository->findByShopIdAndAppItemId(
                food99ShopId: $food99ShopId,
                appItemId: $appItemId,
            );
            $existingItemId = is_object($existingItem) ? (int) data_get($existingItem, 'id') : 0;
            $existingProductId = is_object($existingItem) && is_numeric(data_get($existingItem, 'id_produto'))
                ? (int) data_get($existingItem, 'id_produto')
                : 0;
            $existingGradeId = is_object($existingItem) && is_numeric(data_get($existingItem, 'id_grade'))
                ? (int) data_get($existingItem, 'id_grade')
                : 0;

            $priceCents = $this->resolveRemotePriceCents($itemPayload);
            $priceAmount = round($priceCents / 100, 5);

            if ($existingProductId <= 0 || $existingGradeId <= 0) {
                [$existingProductId, $existingGradeId] = $this->ensureErpProductAndGradeForRemoteItem(
                    idCadastro: $idCadastro,
                    erpUserId: $erpUserId,
                    itemName: $itemName,
                    appItemId: $appItemId,
                    priceAmount: $priceAmount,
                );
                $mappingCreated++;
            } else {
                $mappingUpdated++;
            }

            $values = [
                'food99_shop_category_id' => $shopCategoryId,
                'id_cadastro' => $idCadastro,
                'id_produto' => $existingProductId,
                'id_grade' => $existingGradeId > 0 ? $existingGradeId : null,
                'app_external_id' => data_get($itemPayload, 'app_external_id'),
                'item_name' => mb_substr($itemName, 0, 50),
                'short_desc' => mb_substr((string) data_get($itemPayload, 'short_desc', ''), 0, 300) ?: null,
                'head_img' => mb_substr((string) data_get($itemPayload, 'head_img', ''), 0, 300) ?: null,
                'price_source' => 'grade',
                'price_amount' => $priceAmount,
                'price_cents' => $priceCents,
                'tax_rate' => is_numeric(data_get($itemPayload, 'tax_rate')) ? (int) data_get($itemPayload, 'tax_rate') : null,
                'is_active' => (bool) data_get($itemPayload, 'is_active', true),
                'publish_status' => 'published',
                'payload_snapshot' => $itemPayload,
            ];

            $item = $this->shopItemRepository->updateOrCreate(
                attributes: [
                    'food99_shop_id' => $food99ShopId,
                    'app_item_id' => $appItemId,
                ],
                values: $values,
            );

            if ($shopCategoryId !== null && is_numeric(data_get($item, 'id'))) {
                $this->shopCategoryItemRepository->updateOrCreate(
                    attributes: [
                        'food99_shop_category_id' => $shopCategoryId,
                        'food99_shop_item_id' => (int) data_get($item, 'id'),
                    ],
                    values: [
                        'sort_order' => is_numeric(data_get($itemPayload, 'sort_order')) ? (int) data_get($itemPayload, 'sort_order') : 0,
                    ],
                );
            }

            $itemCount++;
        }

        return [
            'app_shop_id' => $appShopId,
            'food99_shop_id' => $food99ShopId,
            'id_cadastro' => $idCadastro,
            'remote_counts' => [
                'menus' => count($remoteMenus),
                'categories' => count($remoteCategories),
                'items' => count($remoteItems),
            ],
            'synced_counts' => [
                'menus' => $menuCount,
                'categories' => $categoryCount,
                'items' => $itemCount,
                'mappings_created' => $mappingCreated,
                'mappings_existing' => $mappingUpdated,
            ],
        ];
    }

    /**
     * Consulta catalogo publicado diretamente na API da 99Food.
     *
     * @param string $appShopId    app_shop_id da loja
     * @param int    $food99ShopId ID interno da loja
     *
     * @return array<string, mixed> [menus, categories, items]
     */
    private function fetchPublishedCatalogFromFood99(string $appShopId, int $food99ShopId): array
    {
        $tokenRecord = $this->storeTokenRepository->findByAppShopId($appShopId);
        $authToken = is_object($tokenRecord) ? trim((string) data_get($tokenRecord, 'auth_token')) : '';

        if ($authToken === '') {
            throw new ApiException(
                msg: 'Token de autenticacao nao encontrado para a loja. Execute food99/auth/token/get antes de consultar published.',
                code: 422,
            );
        }

        $response = $this->food99Request(
            method: 'GET',
            path: '/v1/item/item/list',
            query: [
                'auth_token' => $authToken,
                'app_shop_id' => $appShopId,
            ],
        );

        $data = data_get($response, 'data');
        if (! is_array($data)) {
            throw new ApiException(
                msg: 'Resposta invalida da 99Food ao consultar catalogo publicado.',
                code: 502,
            );
        }

        return [
            'app_shop_id' => $appShopId,
            'food99_shop_id' => $food99ShopId,
            'menus' => (array) data_get($data, 'menus', []),
            'categories' => (array) data_get($data, 'categories', []),
            'items' => (array) data_get($data, 'items', []),
        ];
    }

    /**
     * Substitui o vinculo de itens de uma categoria.
     *
     * @param array<string, mixed> $input Dados de vinculo
     *
     * @return array<string, mixed> Resultado da operacao
     */
    public function linkCategoryItems(array $input): array
    {
        $shop = $this->resolveShopByAppShopId((string) $input['app_shop_id']);
        $category = $this->resolveCategoryByAppCategoryId(
            food99ShopId: (int) $shop->id,
            appCategoryId: (string) $input['app_category_id'],
        );

        $appItemIds = array_values(
            array_filter(
                array_map(
                    static fn ($value): string => trim((string) $value),
                    (array) $input['app_item_ids'],
                ),
                static fn (string $value): bool => $value !== '',
            ),
        );

        if ($appItemIds === []) {
            throw new ApiException(
                msg: 'Informe ao menos um app_item_id para vinculo de categoria.',
                code: 422,
            );
        }

        $items = $this->shopItemRepository->findByShopIdAndAppItemIds(
            food99ShopId: (int) $shop->id,
            appItemIds: $appItemIds,
        );

        $foundByAppItemId = $items->keyBy('app_item_id');
        $missingIds = array_values(array_diff($appItemIds, $foundByAppItemId->keys()->all()));

        if ($missingIds !== []) {
            throw new ApiException(
                msg: 'Itens nao encontrados para vinculo: ' . implode(', ', $missingIds),
                code: 422,
            );
        }

        $shopItemIds = [];
        foreach ($appItemIds as $appItemId) {
            $item = $foundByAppItemId->get($appItemId);
            if ($item === null) {
                continue;
            }
            $shopItemIds[] = (int) $item->id;
        }

        $this->shopCategoryItemRepository->replaceLinksByCategory(
            categoryId: (int) $category->id,
            shopItemIds: $shopItemIds,
        );

        return [
            'app_shop_id' => (string) $input['app_shop_id'],
            'app_category_id' => (string) $input['app_category_id'],
            'app_item_ids' => $appItemIds,
            'linked_count' => count($appItemIds),
        ];
    }

    /**
     * Resolve loja a partir do app_shop_id.
     *
     * @param string $appShopId app_shop_id da loja
     *
     * @return object Registro da loja
     */
    private function resolveShopByAppShopId(string $appShopId): object
    {
        $idCadastro = $this->resolveAuthenticatedIdCadastro();

        $shop = $this->shopRepository->findOwnedByAppShopId(
            idCadastro: $idCadastro,
            appShopId: $appShopId,
        );

        if (! is_object($shop)) {
            throw new ApiException(
                msg: 'Loja nao encontrada para o cliente logado e app_shop_id informado.',
                code: 404,
            );
        }

        return $shop;
    }

    /**
     * Resolve menu interno pelo app_menu_id.
     *
     * @param int    $food99ShopId ID interno da loja
     * @param string $appMenuId    app_menu_id do menu
     *
     * @return object Menu encontrado
     */
    private function resolveMenuByAppMenuId(int $food99ShopId, string $appMenuId): object
    {
        $menu = $this->shopMenuRepository->findByShopIdAndAppMenuId(
            food99ShopId: $food99ShopId,
            appMenuId: $appMenuId,
        );

        if (! is_object($menu)) {
            throw new ApiException(
                msg: 'Menu nao encontrado para o app_menu_id informado.',
                code: 404,
            );
        }

        return $menu;
    }

    /**
     * Resolve categoria interna pelo app_category_id.
     *
     * @param int    $food99ShopId  ID interno da loja
     * @param string $appCategoryId app_category_id da categoria
     *
     * @return Food99ShopCategory Categoria encontrada
     */
    private function resolveCategoryByAppCategoryId(int $food99ShopId, string $appCategoryId): object
    {
        $category = $this->shopCategoryRepository->findByShopIdAndAppCategoryId(
            food99ShopId: $food99ShopId,
            appCategoryId: $appCategoryId,
        );

        if (! is_object($category)) {
            throw new ApiException(
                msg: 'Categoria nao encontrada para o app_category_id informado.',
                code: 404,
            );
        }

        return $category;
    }

    /**
     * Resolve os valores de price_amount e price_cents a partir dos dados de entrada.
     *
     * @param array<string, mixed> $input Dados recebidos
     *
     * @return array{0: float|null, 1: int} [price_amount, price_cents]
     */
    private function resolvePriceValues(array $input): array
    {
        $hasPriceCents = isset($input['price_cents']) && $input['price_cents'] !== null && $input['price_cents'] !== '';
        $hasPriceAmount = isset($input['price_amount']) && $input['price_amount'] !== null && $input['price_amount'] !== '';

        if (! $hasPriceCents && ! $hasPriceAmount) {
            throw new ApiException(
                msg: 'Informe price_cents ou price_amount para o item.',
                code: 422,
            );
        }

        if ($hasPriceCents) {
            $priceCents = (int) $input['price_cents'];
            $priceAmount = round($priceCents / 100, 5);

            return [$priceAmount, $priceCents];
        }

        $priceAmountFloat = (float) $input['price_amount'];
        $priceCents = (int) round($priceAmountFloat * 100);

        return [round($priceAmountFloat, 5), $priceCents];
    }

    /**
     * Resolve o id_cadastro do usuario autenticado.
     *
     * @return int ID do cadastro logado
     */
    private function resolveAuthenticatedIdCadastro(): int
    {
        $idCadastro = $this->usuarioLogado->getIdCadastroLogado();
        if (! is_numeric($idCadastro) || (int) $idCadastro <= 0) {
            throw new ApiException(
                msg: 'Nao foi possivel identificar o id_cadastro do usuario logado.',
                code: 403,
            );
        }

        return (int) $idCadastro;
    }

    private function resolveAuthenticatedIdUsuario(): int
    {
        $idUsuario = $this->usuarioLogado->getIdUsuarioLogado();
        if (! is_numeric($idUsuario) || (int) $idUsuario <= 0) {
            throw new ApiException(
                msg: 'Nao foi possivel identificar o id_usuario do usuario logado.',
                code: 403,
            );
        }

        return (int) $idUsuario;
    }

    /**
     * @return array{0:int,1:?int}
     */
    private function ensureErpProductAndGradeForRemoteItem(
        int $idCadastro,
        int $erpUserId,
        string $itemName,
        string $appItemId,
        float $priceAmount,
    ): array {
        $codigoBarra = $this->buildProductBarcode($idCadastro, $appItemId);

        $produto = $this->produtoRepository->create([
            'descricao' => mb_substr($itemName !== '' ? $itemName : 'Item 99Food', 0, 255),
            'id_cadastro' => $idCadastro,
            'id_usuario' => $erpUserId,
            'data_cadastro' => now(),
            'ativo' => 1,
            'codigo_barra' => $codigoBarra,
            'barra' => $codigoBarra,
            'ean' => $codigoBarra,
            'identificacao_interna' => mb_substr('99F-' . ($appItemId !== '' ? $appItemId : uniqid()), 0, 60),
            'custo' => max(0.01, $priceAmount),
            'custo_medio_venda' => max(0.01, $priceAmount),
            'custo_medio_venda_atacado' => max(0.01, $priceAmount),
            'qtd_minima' => 0,
            'locacao_quantidade' => 0,
        ]);

        $idProduto = (int) data_get($produto, 'id');
        if ($idProduto <= 0) {
            throw new ApiException(msg: 'Nao foi possivel criar produto no ERP para o item remoto da 99Food.', code: 500);
        }

        $grade = $this->gradeRepository->findLatestByProdutoId($idProduto);

        $idGrade = is_object($grade) && is_numeric(data_get($grade, 'id_grade'))
            ? (int) data_get($grade, 'id_grade')
            : null;

        return [$idProduto, $idGrade];
    }

    private function resolveRemotePriceCents(array $itemPayload): int
    {
        $priceCents = data_get($itemPayload, 'price_cents');
        if (is_numeric($priceCents)) {
            return (int) $priceCents;
        }

        $priceAmount = data_get($itemPayload, 'price_amount');
        if (is_numeric($priceAmount)) {
            return (int) round(((float) $priceAmount) * 100);
        }

        $price = data_get($itemPayload, 'price');
        if (is_numeric($price)) {
            return (int) $price;
        }

        return 0;
    }

    private function buildProductBarcode(int $idCadastro, string $appItemId): string
    {
        $seed = trim($appItemId) !== '' ? $appItemId : uniqid((string) $idCadastro, true);
        $hash = preg_replace('/\D+/', '', (string) crc32($idCadastro . '|' . $seed));
        if (! is_string($hash) || $hash === '') {
            $hash = (string) random_int(1000000000, 9999999999);
        }

        return str_pad(substr($hash, 0, 12), 12, '0', STR_PAD_LEFT);
    }

    private function resolveUpsertAppItemId(
        int $food99ShopId,
        int $idProduto,
        ?int $idGrade,
        mixed $requestedAppItemId,
    ): string {
        $existingByProdutoGrade = $this->shopItemRepository->findByShopIdAndProdutoGrade(
            food99ShopId: $food99ShopId,
            idProduto: $idProduto,
            idGrade: $idGrade,
        );
        $existingAppItemId = trim((string) data_get($existingByProdutoGrade, 'app_item_id', ''));

        $normalizedRequested = trim((string) ($requestedAppItemId ?? ''));
        if ($normalizedRequested !== '') {
            // Preserva a identidade ja persistida para o mesmo produto/grade.
            if ($existingAppItemId !== '' && $existingAppItemId !== $normalizedRequested) {
                return $existingAppItemId;
            }

            $existingByAppItemId = $this->shopItemRepository->findByShopIdAndAppItemId(
                food99ShopId: $food99ShopId,
                appItemId: $normalizedRequested,
            );
            if (is_object($existingByAppItemId)) {
                $existingProduct = (int) data_get($existingByAppItemId, 'id_produto', 0);
                $existingGradeRaw = data_get($existingByAppItemId, 'id_grade');
                $existingGrade = is_numeric($existingGradeRaw) ? (int) $existingGradeRaw : null;

                if ($existingProduct !== $idProduto || $existingGrade !== $idGrade) {
                    throw new ApiException(
                        msg: 'app_item_id ja utilizado por outro produto/grade nesta loja.',
                        code: 422,
                    );
                }
            }

            return $normalizedRequested;
        }

        if ($existingAppItemId !== '') {
            return $existingAppItemId;
        }

        return $this->generateUniqueAppItemId(
            food99ShopId: $food99ShopId,
            idProduto: $idProduto,
            idGrade: $idGrade,
        );
    }

    private function generateUniqueAppItemId(int $food99ShopId, int $idProduto, ?int $idGrade): string
    {
        $gradeSuffix = $idGrade !== null ? (string) $idGrade : 'na';
        $base = 'p' . $idProduto . '_g' . $gradeSuffix;
        $candidate = mb_substr($base, 0, 120);

        $seq = 1;
        while (is_object($this->shopItemRepository->findByShopIdAndAppItemId($food99ShopId, $candidate))) {
            $suffix = '_' . $seq;
            $maxBaseLength = 120 - mb_strlen($suffix);
            $candidate = mb_substr($base, 0, max(1, $maxBaseLength)) . $suffix;
            $seq++;
        }

        return $candidate;
    }
}
