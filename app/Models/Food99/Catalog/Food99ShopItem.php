<?php

declare(strict_types=1);

namespace App\Models\Food99\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent de item por loja da 99Food.
 */
class Food99ShopItem extends Model
{
    /**
     * Nome da conexao de banco usada por este model.
     */
    protected $connection = 'mysql_marketplace';

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'food99_shop_items';

    /**
     * Campos permitidos para mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'food99_shop_id',
        'food99_shop_category_id',
        'id_cadastro',
        'id_produto',
        'id_grade',
        'app_item_id',
        'app_external_id',
        'item_name',
        'short_desc',
        'head_img',
        'price_source',
        'price_amount',
        'price_cents',
        'tax_rate',
        'is_active',
        'publish_status',
        'last_published_at',
        'last_error_message',
        'payload_snapshot',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:5',
            'price_cents' => 'integer',
            'tax_rate' => 'integer',
            'is_active' => 'boolean',
            'last_published_at' => 'datetime',
            'payload_snapshot' => 'array',
        ];
    }
}
