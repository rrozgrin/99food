<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Catalog;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia da pivot categoria-item da 99Food.
 */
interface Food99ShopCategoryItemRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca relacao categoria-item.
     *
     * @param  int  $categoryId  ID interno da categoria
     * @param  int  $itemId  ID interno do item
     * @return object|null Relacao encontrada
     */
    public function findByCategoryIdAndItemId(int $categoryId, int $itemId): ?object;

    /**
     * Retorna relacoes de itens por categorias.
     *
     * @param  array<int, int>  $categoryIds  IDs internos de categoria
     * @return object|null Colecao da pivot
     */
    public function findByCategoryIds(array $categoryIds): ?object;

    /**
     * Remove todas as relacoes de uma categoria.
     *
     * @param  int  $categoryId  ID interno da categoria
     * @return int Quantidade removida
     */
    public function deleteByCategoryId(int $categoryId): int;

    public function deleteByItemId(int $itemId): int;

    public function replaceLinksByCategory(int $categoryId, array $shopItemIds): void;
}
