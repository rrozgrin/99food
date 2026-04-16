<?php

declare(strict_types=1);

namespace App\Models\Food99\Orders;

use Illuminate\Database\Eloquent\Model;

class Food99OrderItem extends Model
{
    protected $connection = 'mysql_marketplace';

    protected $table = 'food99_order_items';

    protected $fillable = [
        'food99_order_id',
        'app_item_id',
        'app_external_id',
        'item_name',
        'amount',
        'sku_price',
        'total_price',
        'real_price',
        'remark',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'food99_order_id' => 'integer',
            'amount' => 'integer',
            'sku_price' => 'integer',
            'total_price' => 'integer',
            'real_price' => 'integer',
            'payload' => 'array',
        ];
    }
}

