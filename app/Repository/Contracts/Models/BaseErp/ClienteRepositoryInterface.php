<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\BaseErp;

use App\Repository\Contracts\RepositoryInterface;

interface ClienteRepositoryInterface extends RepositoryInterface
{
    public function findByCadastroNomeTelefone(int $idCadastro, string $nome, ?string $telefone): ?object;
}
