<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\Food99\Auth;

use App\Models\Food99\Auth\Food99AppCredential;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface;

/**
 * Repositorio Eloquent de credenciais configuradas da 99Food.
 */
class Food99AppCredentialEloquentRepository extends EloquentRepository implements Food99AppCredentialRepositoryInterface
{
    /**
     * Conexao usada por este repositorio.
     */
    protected string $connection = 'mysql_marketplace';

    /**
     * @param Food99AppCredential $model Model de credencial da 99Food
     */
    public function __construct(Food99AppCredential $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findLatestByAppId(string $appId): ?object
    {
        return $this->model
            ->newQuery()
            ->where('app_id', $appId)
            ->orderByDesc('id')
            ->first();
    }
}
