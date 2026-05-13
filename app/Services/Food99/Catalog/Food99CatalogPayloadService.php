<?php

declare(strict_types=1);

namespace App\Services\Food99\Catalog;

use App\Exceptions\ApiException;
use Illuminate\Support\Collection;
use App\Services\Auth\UsuarioLogadoService;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopMenuRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryItemRepositoryInterface;

/**
 * Montagem de payload de catalogo para publicacao na 99Food.
 *
 * Estrutura alvo:
 * - menus
 * - categories
 * - items
 * - auth_token (quando disponivel localmente)
 */
class Food99CatalogPayloadService
{
    /**
     * @param Food99ShopMenuRepositoryInterface         $shopMenuRepository         Repositorio de menus
     * @param Food99ShopCategoryRepositoryInterface     $shopCategoryRepository     Repositorio de categorias
     * @param Food99ShopItemRepositoryInterface         $shopItemRepository         Repositorio de itens
     * @param Food99ShopCategoryItemRepositoryInterface $shopCategoryItemRepository Repositorio da pivot categoria-item
     * @param Food99StoreTokenRepositoryInterface       $storeTokenRepository       Repositorio de token por loja
     */
    public function __construct(
        private readonly Food99ShopMenuRepositoryInterface $shopMenuRepository,
        private readonly Food99ShopCategoryRepositoryInterface $shopCategoryRepository,
        private readonly Food99ShopItemRepositoryInterface $shopItemRepository,
        private readonly Food99ShopCategoryItemRepositoryInterface $shopCategoryItemRepository,
        private readonly Food99StoreTokenRepositoryInterface $storeTokenRepository,
        private readonly UsuarioLogadoService $usuarioLogado,
        private readonly Food99ShopRepositoryInterface $shopRepository,
    ) {}

    /**
     * Monta payload de upload de menu conforme contrato da 99Food.
     *
     * @param string             $appShopId        app_shop_id da loja
     * @param array<int, string> $appItemIdsFilter Filtro opcional de app_item_id para publicacao seletiva
     *
     * @return array<string, mixed> Estrutura de payload pronta para envio
     */
    public function buildUploadPayloadPreview(string $appShopId, array $appItemIdsFilter = []): array
    {
        $shop = $this->resolveShopByAppShopId($appShopId);
        $food99ShopId = (int) data_get($shop, 'id');
        $normalizedAppItemIdsFilter = collect($appItemIdsFilter)
            ->filter(static fn ($itemId): bool => is_string($itemId))
            ->map(static fn (string $itemId): string => trim($itemId))
            ->filter(static fn (string $itemId): bool => $itemId !== '')
            ->unique()
            ->values()
            ->all();
        $isSelectivePublish = $normalizedAppItemIdsFilter !== [];
        $requestedItemIdsMap = array_fill_keys($normalizedAppItemIdsFilter, true);
        $resolvedRequestedItemIds = [];

        $menus = collect($this->shopMenuRepository->findActiveByShopId($food99ShopId));
        $categories = collect($this->shopCategoryRepository->findActiveByShopId($food99ShopId));
        $items = collect($this->shopItemRepository->findActiveByShopId($food99ShopId));
        $availableAppItemIds = $items
            ->map(static fn ($item): string => trim((string) data_get($item, 'app_item_id')))
            ->filter(static fn (string $appItemId): bool => $appItemId !== '')
            ->unique()
            ->values()
            ->all();

        if ($menus->isEmpty()) {
            throw new ApiException(
                msg: 'Nenhum menu ativo encontrado para a loja informada.',
                code: 422,
            );
        }

        if ($categories->isEmpty()) {
            throw new ApiException(
                msg: 'Nenhuma categoria ativa encontrada para a loja informada.',
                code: 422,
            );
        }

        if ($items->isEmpty()) {
            throw new ApiException(
                msg: 'Nenhum item ativo encontrado para a loja informada.',
                code: 422,
            );
        }

        $categoryIds = $categories
            ->pluck('id')
            ->filter(static fn ($id): bool => is_numeric($id))
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $categoryItemLinks = collect($this->shopCategoryItemRepository->findByCategoryIds($categoryIds));
        $itemsById = $items->keyBy(static fn ($item): int => (int) $item->id);

        $itemIdsByCategoryId = $this->groupItemIdsByCategory(
            items: $items,
            categoryItemLinks: $categoryItemLinks,
            itemsById: $itemsById,
        );

        $referencedItemIds = [];
        $appCategoryIdsByMenu = [];
        $categoriesPayload = [];

        foreach ($categories as $category) {
            $categoryId = (int) $category->id;
            $appCategoryId = trim((string) data_get($category, 'app_category_id'));
            $categoryName = trim((string) data_get($category, 'category_name'));

            if ($appCategoryId === '' || $categoryName === '') {
                throw new ApiException(
                    msg: sprintf('Categoria id=%d sem app_category_id/category_name valido.', $categoryId),
                    code: 422,
                );
            }

            $appItemIds = [];
            foreach ($itemIdsByCategoryId[$categoryId] ?? [] as $itemId) {
                $item = $itemsById->get($itemId);
                if ($item === null) {
                    continue;
                }

                $appItemId = trim((string) data_get($item, 'app_item_id'));
                if ($appItemId === '') {
                    continue;
                }

                if ($isSelectivePublish && ! isset($requestedItemIdsMap[$appItemId])) {
                    continue;
                }

                if (! in_array($appItemId, $appItemIds, true)) {
                    $appItemIds[] = $appItemId;
                }

                if ($isSelectivePublish) {
                    $resolvedRequestedItemIds[$appItemId] = true;
                }

                $referencedItemIds[$itemId] = true;
            }

            if ($appItemIds === []) {
                if ($isSelectivePublish) {
                    continue;
                }

                throw new ApiException(
                    msg: sprintf('Categoria %s sem itens vinculados para publicacao.', $appCategoryId),
                    code: 422,
                );
            }

            $categoriesPayload[] = [
                'app_category_id' => $appCategoryId,
                'category_name' => $categoryName,
                'app_item_ids' => $appItemIds,
            ];

            $menuId = (int) data_get($category, 'food99_shop_menu_id');
            if (! isset($appCategoryIdsByMenu[$menuId])) {
                $appCategoryIdsByMenu[$menuId] = [];
            }

            if (! in_array($appCategoryId, $appCategoryIdsByMenu[$menuId], true)) {
                $appCategoryIdsByMenu[$menuId][] = $appCategoryId;
            }
        }

        $menusPayload = [];
        foreach ($menus as $menu) {
            $menuId = (int) $menu->id;
            $appMenuId = trim((string) data_get($menu, 'app_menu_id'));
            $menuName = trim((string) data_get($menu, 'menu_name'));
            $appCategoryIds = $appCategoryIdsByMenu[$menuId] ?? [];

            if ($appMenuId === '' || $menuName === '') {
                throw new ApiException(
                    msg: sprintf('Menu id=%d sem app_menu_id/menu_name valido.', $menuId),
                    code: 422,
                );
            }

            if ($appCategoryIds === []) {
                if ($isSelectivePublish) {
                    continue;
                }

                throw new ApiException(
                    msg: sprintf('Menu %s sem categorias vinculadas para publicacao.', $appMenuId),
                    code: 422,
                );
            }

            $menusPayload[] = [
                'app_menu_id' => $appMenuId,
                'menu_name' => $menuName,
                'app_category_ids' => $appCategoryIds,
            ];
        }

        $itemsPayload = [];
        foreach (array_keys($referencedItemIds) as $itemId) {
            $item = $itemsById->get((int) $itemId);
            if ($item === null) {
                continue;
            }

            $itemsPayload[] = $this->mapItemPayload($item);
        }

        if ($isSelectivePublish) {
            $notResolvedItemIds = array_values(array_diff(
                $normalizedAppItemIdsFilter,
                array_keys($resolvedRequestedItemIds),
            ));

            if ($notResolvedItemIds !== []) {
                throw new ApiException(
                    msg: sprintf(
                        'Itens nao encontrados para publicacao nesta loja: %s. app_item_id disponiveis: %s',
                        implode(', ', $notResolvedItemIds),
                        $availableAppItemIds !== [] ? implode(', ', $availableAppItemIds) : '[nenhum item ativo]',
                    ),
                    code: 422,
                );
            }
        }

        if ($itemsPayload === []) {
            throw new ApiException(
                msg: 'Nao foi possivel montar lista de itens para publicacao.',
                code: 422,
            );
        }

        $tokenRecord = $this->storeTokenRepository->findByAppShopId($appShopId);
        $authToken = is_object($tokenRecord) ? trim((string) data_get($tokenRecord, 'auth_token')) : '';

        $payload = [
            'menus' => $menusPayload,
            'categories' => $categoriesPayload,
            'items' => $itemsPayload,
        ];

        if ($authToken !== '') {
            $payload['auth_token'] = $authToken;
        }

        return [
            'app_shop_id' => $appShopId,
            'food99_shop_id' => $food99ShopId,
            'token_found' => $authToken !== '',
            'is_selective_publish' => $isSelectivePublish,
            'requested_app_item_ids' => $normalizedAppItemIdsFilter,
            'payload' => $payload,
            'stats' => [
                'menus' => count($menusPayload),
                'categories' => count($categoriesPayload),
                'items' => count($itemsPayload),
            ],
        ];
    }

    /**
     * Resolve loja interna a partir do app_shop_id.
     *
     * @param string $appShopId app_shop_id da loja
     *
     * @return object Registro da loja
     */
    private function resolveShopByAppShopId(string $appShopId): object
    {
        $idCadastro = $this->resolveAuthenticatedIdCadastro();

        $shop = $this->shopRepository->findOwnedByAppShopId($idCadastro, $appShopId);

        if (! is_object($shop)) {
            throw new ApiException(
                msg: 'Loja nao encontrada para o cliente logado e app_shop_id informado.',
                code: 404,
            );
        }

        return $shop;
    }

    /**
     * Agrupa IDs de itens por categoria combinando pivot e fallback direto.
     *
     * @param Collection<int, object> $items             Colecao de itens da loja
     * @param Collection<int, object> $categoryItemLinks Colecao pivot categoria-item
     * @param Collection<int, object> $itemsById         Itens indexados por ID interno
     *
     * @return array<int, array<int, int>> [category_id => [item_id, ...]]
     */
    private function groupItemIdsByCategory(
        Collection $items,
        Collection $categoryItemLinks,
        Collection $itemsById,
    ): array {
        $itemIdsByCategoryId = [];

        foreach ($categoryItemLinks as $link) {
            $categoryId = (int) data_get($link, 'food99_shop_category_id');
            $itemId = (int) data_get($link, 'food99_shop_item_id');

            if ($categoryId <= 0 || $itemId <= 0 || ! $itemsById->has($itemId)) {
                continue;
            }

            if (! isset($itemIdsByCategoryId[$categoryId])) {
                $itemIdsByCategoryId[$categoryId] = [];
            }

            if (! in_array($itemId, $itemIdsByCategoryId[$categoryId], true)) {
                $itemIdsByCategoryId[$categoryId][] = $itemId;
            }
        }

        // Fallback para cenarios sem pivot preenchida.
        foreach ($items as $item) {
            $categoryId = data_get($item, 'food99_shop_category_id');
            if (! is_numeric($categoryId)) {
                continue;
            }

            $categoryId = (int) $categoryId;
            $itemId = (int) data_get($item, 'id');
            if ($categoryId <= 0 || $itemId <= 0) {
                continue;
            }

            if (! isset($itemIdsByCategoryId[$categoryId])) {
                $itemIdsByCategoryId[$categoryId] = [];
            }

            if (! in_array($itemId, $itemIdsByCategoryId[$categoryId], true)) {
                $itemIdsByCategoryId[$categoryId][] = $itemId;
            }
        }

        return $itemIdsByCategoryId;
    }

    /**
     * Mapeia item local para o contrato ItemStruct da 99Food.
     *
     * @param object $item Item local
     *
     * @return array<string, mixed> Item no formato de upload
     */
    private function mapItemPayload(object $item): array
    {
        $appItemId = trim((string) data_get($item, 'app_item_id'));
        $itemName = trim((string) data_get($item, 'item_name'));

        if ($appItemId === '' || $itemName === '') {
            throw new ApiException(
                msg: sprintf('Item id=%d sem app_item_id/item_name valido.', (int) data_get($item, 'id')),
                code: 422,
            );
        }

        $payload = [
            'app_item_id' => $appItemId,
            'item_name' => $itemName,
            'price' => $this->resolvePriceCents($item),
        ];

        $appExternalId = trim((string) data_get($item, 'app_external_id'));
        if ($appExternalId !== '') {
            $payload['app_external_id'] = $appExternalId;
        }

        $shortDesc = trim((string) data_get($item, 'short_desc'));
        if ($shortDesc !== '') {
            $payload['short_desc'] = $shortDesc;
        }

        $headImg = trim((string) data_get($item, 'head_img'));
        if ($headImg !== '') {
            $payload['head_img'] = $headImg;
        }

        $taxRate = data_get($item, 'tax_rate');
        if (is_numeric($taxRate)) {
            $payload['tax_rate'] = (int) $taxRate;
        }

        return $payload;
    }

    /**
     * Resolve preco em cents para envio no contrato da 99Food.
     *
     * @param object $item Item local
     *
     * @return int Valor em cents
     */
    private function resolvePriceCents(object $item): int
    {
        $priceCents = data_get($item, 'price_cents');
        if (is_numeric($priceCents)) {
            return (int) $priceCents;
        }

        $priceAmount = data_get($item, 'price_amount');
        if (is_numeric($priceAmount)) {
            return (int) round(((float) $priceAmount) * 100);
        }

        throw new ApiException(
            msg: sprintf('Item %s sem preco configurado (price_cents/price_amount).', (string) data_get($item, 'app_item_id')),
            code: 422,
        );
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
}
