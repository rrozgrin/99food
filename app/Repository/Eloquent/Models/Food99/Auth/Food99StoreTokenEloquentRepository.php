<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Auth;

use App\Models\Food99\Auth\Food99StoreToken;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;

/**
 * Repositorio Eloquent para token da 99Food por loja.
 *
 * Usa conexao mysql_marketplace para dados de integracao externa.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class Food99StoreTokenEloquentRepository extends EloquentRepository implements Food99StoreTokenRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * Inicializa o repositorio com o model de token da 99Food.
     *
     * @param Food99StoreToken $model Model do token por loja
     */
    public function __construct(Food99StoreToken $model)
    {
        parent::__construct($model);
    }

    /**
     * Busca o token da 99Food por app_shop_id.
     *
     * @param string $appShopId ID externo da loja
     *
     * @return object|null Registro encontrado ou null
     */
    public function findByAppShopId(string $appShopId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('app_shop_id', $appShopId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByAppShopIds(array $appShopIds): ?object
    {
        if ($appShopIds === []) {
            return $this->model->newCollection();
        }

        return $this->model
            ->newQuery()
            ->select(['app_shop_id', 'auth_token', 'expires_at', 'last_retrieved_at', 'last_refreshed_at'])
            ->whereIn('app_shop_id', $appShopIds)
            ->get();
    }

    /**
     * Cria ou atualiza o token da loja da 99Food.
     *
     * @param string              $appShopId ID externo da loja
     * @param array<string, mixed> $payload   Dados para persistencia
     *
     * @return object Registro criado ou atualizado
     */
    public function upsertByAppShopId(string $appShopId, array $payload): object
    {
        return $this->updateOrCreate(
            attributes: ['app_shop_id' => $appShopId],
            values: $payload,
        );
    }
}
