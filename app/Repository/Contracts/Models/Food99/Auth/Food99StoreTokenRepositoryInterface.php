<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Auth;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia para tokens de loja da 99Food.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
interface Food99StoreTokenRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca o token da 99Food por app_shop_id.
     *
     * @param string $appShopId ID externo da loja na 99Food
     *
     * @return object|null Registro encontrado ou null
     */
    public function findByAppShopId(string $appShopId): ?object;

    /**
     * Busca tokens por lista de app_shop_id.
     *
     * @param array<int, string> $appShopIds IDs externos das lojas
     *
     * @return object|null Colecao de tokens
     */
    public function findByAppShopIds(array $appShopIds): ?object;

    /**
     * Cria ou atualiza o token da loja da 99Food.
     *
     * @param string              $appShopId ID externo da loja na 99Food
     * @param array<string, mixed> $payload   Dados para persistencia
     *
     * @return object Registro criado ou atualizado
     */
    public function upsertByAppShopId(string $appShopId, array $payload): object;
}
