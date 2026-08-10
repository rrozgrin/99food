<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\VendaPagamento;
use App\Repository\Contracts\Models\BaseErp\VendaPagamentoRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

class VendaPagamentoEloquentRepository extends EloquentRepository implements VendaPagamentoRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(VendaPagamento $model)
    {
        parent::__construct($model);
    }
}
