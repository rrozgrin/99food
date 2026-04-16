<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Catalog;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia para itens de loja da 99Food.
 */
interface Food99ShopItemRepositoryInterface extends RepositoryInterface
{
    /**
     * Retorna itens da loja.
     *
     * @param int $food99ShopId ID interno da loja em mysql_marketplace
     *
     * @return object|null Colecao de itens
     */
    public function findByShopId(int $food99ShopId): ?object;

    /**
     * Busca item por loja e app_item_id.
     *
     * @param int    $food99ShopId ID interno da loja
     * @param string $appItemId    ID externo do item
     *
     * @return object|null Item encontrado
     */
    public function findByShopIdAndAppItemId(int $food99ShopId, string $appItemId): ?object;

    /**
     * Busca itens da loja por lista de app_item_id.
     *
     * @param int                $food99ShopId ID interno da loja
     * @param array<int, string> $appItemIds   IDs externos dos itens
     *
     * @return object|null Colecao de itens
     */
    public function findByShopIdAndAppItemIds(int $food99ShopId, array $appItemIds): ?object;

    /**
     * Busca item por loja e par ERP (produto/grade).
     *
     * @param int      $food99ShopId ID interno da loja
     * @param int      $idProduto    ID do produto no ERP
     * @param int|null $idGrade      ID da grade no ERP (quando houver)
     *
     * @return object|null Item encontrado
     */
    public function findByShopIdAndProdutoGrade(int $food99ShopId, int $idProduto, ?int $idGrade): ?object;

    /**
     * Retorna itens ativos por loja.
     *
     * @param int $food99ShopId ID interno da loja em mysql_marketplace
     *
     * @return object|null Colecao de itens
     */
    public function findActiveByShopId(int $food99ShopId): ?object;

    /**
     * Marca itens como publicados.
     *
     * @param int                  $food99ShopId            ID interno da loja
     * @param array<int, string>   $appItemIds              IDs externos dos itens
     * @param array<string, array> $itemPayloadByAppItemId  Payload enviado por item
     */
    public function markPublishedByShopAndAppItemIds(
        int $food99ShopId,
        array $appItemIds,
        array $itemPayloadByAppItemId = [],
    ): void;

    /**
     * Marca itens como falhos.
     *
     * @param int                $food99ShopId ID interno da loja
     * @param array<int, string> $appItemIds   IDs externos dos itens
     * @param string             $errorMessage Mensagem de erro
     */
    public function markFailedByShopAndAppItemIds(int $food99ShopId, array $appItemIds, string $errorMessage): void;

    public function updateCategoryIdByIds(int $categoryId, array $itemIds): int;
}
