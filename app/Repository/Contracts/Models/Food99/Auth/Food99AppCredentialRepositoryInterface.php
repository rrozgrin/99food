<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\Food99\Auth;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato de persistencia para credenciais da 99Food.
 */
interface Food99AppCredentialRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca a credencial mais recente por app_id.
     *
     * @param  string  $appId  App ID configurado
     * @return object|null Credencial encontrada
     */
    public function findLatestByAppId(string $appId): ?object;
}
