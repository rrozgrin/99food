<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\BaseErp;

use App\Repository\Contracts\RepositoryInterface;

interface VendaRepositoryInterface extends RepositoryInterface
{
    public function findIdByCadastroOrigemDescricao(int $idCadastro, string $origemVenda, string $descricaoVenda): ?int;

    public function updateSituacaoById(int $idVenda, string $situacao): void;
}
