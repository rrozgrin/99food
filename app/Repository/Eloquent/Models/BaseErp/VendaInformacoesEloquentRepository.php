<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\VendaInformacoes;
use App\Repository\Contracts\Models\BaseErp\VendaInformacoesRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

class VendaInformacoesEloquentRepository extends EloquentRepository implements VendaInformacoesRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(VendaInformacoes $model)
    {
        parent::__construct($model);
    }
}
