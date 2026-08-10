<?php

declare(strict_types=1);

namespace App\Models\Food99\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent da pivot entre categoria e item da 99Food.
 */
class Food99ShopCategoryItem extends Model
{
    /**
     * Nome da conexao de banco usada por este model.
     */
    protected $connection = 'mysql_marketplace';

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'food99_shop_category_items';

    /**
     * Campos permitidos para mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'food99_shop_category_id',
        'food99_shop_item_id',
        'sort_order',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
