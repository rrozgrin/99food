<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Auth;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia para lojas locais vinculadas a 99Food.
 */
interface Food99ShopRepositoryInterface extends RepositoryInterface
{
    /**
     * Lista lojas do cliente logado.
     *
     * @param int $idCadastro ID do cliente no hub
     *
     * @return object|null Colecao de lojas
     */
    public function findOwnedByIdCadastro(int $idCadastro): ?object;

    /**
     * Busca loja do cliente logado por app_shop_id.
     *
     * @param int    $idCadastro ID do cliente no hub
     * @param string $appShopId  ID externo da loja
     *
     * @return object|null Loja encontrada
     */
    public function findOwnedByAppShopId(int $idCadastro, string $appShopId): ?object;

    /**
     * Busca loja por credencial configurada e app_shop_id.
     *
     * @param int    $credentialId ID interno da credencial
     * @param string $appShopId    ID externo da loja
     *
     * @return object|null Loja encontrada
     */
    public function findByCredentialAndAppShopId(int $credentialId, string $appShopId): ?object;
}
