<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\Cliente;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\BaseErp\ClienteRepositoryInterface;

class ClienteEloquentRepository extends EloquentRepository implements ClienteRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(Cliente $model)
    {
        parent::__construct($model);
    }

    public function findByCadastroNomeTelefone(int $idCadastro, string $nome, ?string $telefone): ?object
    {
        $query = $this->model
            ->newQuery()
            ->where('id_cadastro', $idCadastro)
            ->where('nome', $nome);

        if (is_string($telefone) && trim($telefone) !== '') {
            $query->where('telefone', trim($telefone));
        }

        return $query->first();
    }
}
