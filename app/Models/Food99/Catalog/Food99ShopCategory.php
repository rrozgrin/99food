<?php

declare(strict_types=1);

namespace App\Models\Food99\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent de categoria por loja/menu da 99Food.
 */
class Food99ShopCategory extends Model
{
    /**
     * Nome da conexao de banco usada por este model.
     */
    protected $connection = 'mysql_marketplace';

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'food99_shop_categories';

    /**
     * Campos permitidos para mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'food99_shop_id',
        'food99_shop_menu_id',
        'app_category_id',
        'category_name',
        'sort_order',
        'is_active',
        'metadata',
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
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
