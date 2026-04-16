<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\WebcUsuario;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\BaseErp\WebcUsuarioRepositoryInterface;

class WebcUsuarioEloquentRepository extends EloquentRepository implements WebcUsuarioRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(WebcUsuario $model)
    {
        parent::__construct($model);
    }

    public function findActiveIdByCadastro(int $idCadastro): ?int
    {
        $id = $this->model
            ->newQuery()
            ->where('id_cadastro', $idCadastro)
            ->where('ativo', 'A')
            ->orderBy('id')
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
