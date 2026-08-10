<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\BaseErp;

use App\Models\BaseErp\VendaItens;
use App\Repository\Contracts\Models\BaseErp\VendaItensRepositoryInterface;
use App\Repository\Eloquent\EloquentRepository;

class VendaItensEloquentRepository extends EloquentRepository implements VendaItensRepositoryInterface
{
    protected string $connection = 'mysql';

    public function __construct(VendaItens $model)
    {
        parent::__construct($model);
    }
}
