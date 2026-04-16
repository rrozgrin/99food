<?php

declare(strict_types=1);

namespace App\Models\Food99\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food99Order extends Model
{
    protected $connection = 'mysql_marketplace';

    protected $table = 'food99_orders';

    protected $fillable = [
        'food99_app_credential_id',
        'food99_shop_id',
        'food99_webhook_inbound_log_id',
        'event_type',
        'app_shop_id',
        'order_id',
        'status',
        'order_index',
        'remark',
        'country',
        'timezone',
        'pay_type',
        'delivery_type',
        'order_price',
        'real_price',
        'real_pay_price',
        'refund_price',
        'customer_name',
        'customer_phone',
        'create_time',
        'pay_time',
        'complete_time',
        'cancel_time',
        'sync_status',
        'id_venda',
        'erp_sale_id',
        'erp_synced_at',
        'error_message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'food99_app_credential_id' => 'integer',
            'food99_shop_id' => 'integer',
            'food99_webhook_inbound_log_id' => 'integer',
            'status' => 'integer',
            'order_index' => 'integer',
            'pay_type' => 'integer',
            'delivery_type' => 'integer',
            'order_price' => 'integer',
            'real_price' => 'integer',
            'real_pay_price' => 'integer',
            'refund_price' => 'integer',
            'create_time' => 'datetime',
            'pay_time' => 'datetime',
            'complete_time' => 'datetime',
            'cancel_time' => 'datetime',
            'id_venda' => 'integer',
            'erp_synced_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Food99OrderItem::class, 'food99_order_id', 'id');
    }
}
