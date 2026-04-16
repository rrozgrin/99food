<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Auth;

use App\Models\Food99\Auth\Food99Shop;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;

/**
 * Repositorio Eloquent de lojas locais vinculadas a 99Food.
 */
class Food99ShopEloquentRepository extends EloquentRepository implements Food99ShopRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * @param Food99Shop $model Model de loja da 99Food no hub
     */
    public function __construct(Food99Shop $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findOwnedByIdCadastro(int $idCadastro): ?object
    {
        return $this->model
            ->newQuery()
            ->where('id_cadastro', $idCadastro)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findOwnedByAppShopId(int $idCadastro, string $appShopId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('id_cadastro', $idCadastro)
            ->where('app_shop_id', $appShopId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByCredentialAndAppShopId(int $credentialId, string $appShopId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('food99_app_credential_id', $credentialId)
            ->where('app_shop_id', $appShopId)
            ->first();
    }
}
