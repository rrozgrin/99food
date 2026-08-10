<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Seed de exemplo para catalogo 99Food.
 *
 * Cria loja/app_shop_id de sandbox e estrutura minima:
 * - 1 menu
 * - 1 categoria
 * - 2 itens
 * - vinculo categoria-itens
 */
class Food99CatalogSampleSeeder extends Seeder
{
    /**
     * Executa o seed de exemplo.
     */
    public function run(): void
    {
        $connection = 'mysql_marketplace';
        $appShopId = 'wc-sandbox-002';

        $requiredTables = [
            'food99_shops',
            'food99_shop_menus',
            'food99_shop_categories',
            'food99_shop_items',
            'food99_shop_category_items',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                throw new RuntimeException(
                    sprintf('Tabela %s nao encontrada na conexao %s.', $table, $connection),
                );
            }
        }

        $credentialId = $this->resolveCredentialId($connection);
        $shopId = $this->upsertShop($connection, $appShopId, $credentialId);
        $this->upsertToken($connection, $appShopId, $shopId);

        $menuId = $this->upsertMenu($connection, $shopId);
        $categoryId = $this->upsertCategory($connection, $shopId, $menuId);
        $itemIds = $this->upsertItems($connection, $shopId, $categoryId);
        $this->syncCategoryItems($connection, $categoryId, $itemIds);

        if ($this->command !== null) {
            $this->command->info('Food99CatalogSampleSeeder executado com sucesso.');
            $this->command->line('app_shop_id: wc-sandbox-002');
            $this->command->line('menu: menu_principal');
            $this->command->line('categoria: burgers');
            $this->command->line('itens: burger_classico, burger_bacon');
        }
    }

    /**
     * Resolve ou cria credencial de app da 99Food.
     */
    private function resolveCredentialId(string $connection): int
    {
        if (! Schema::connection($connection)->hasTable('food99_app_credentials')) {
            return 1;
        }

        $table = DB::connection($connection)->table('food99_app_credentials');
        $appId = (string) config('services.food99.app_id', 'sample-app-id');
        $environment = 'sandbox';

        $existing = $table
            ->where('environment', $environment)
            ->where('app_id', $appId !== '' ? $appId : 'sample-app-id')
            ->first();

        if (is_object($existing) && is_numeric($existing->id ?? null)) {
            return (int) $existing->id;
        }

        return (int) $table->insertGetId([
            'name' => 'Credencial Sandbox',
            'environment' => $environment,
            'country_code' => 'BR',
            'app_id' => $appId !== '' ? $appId : 'sample-app-id',
            'app_secret' => 'sample-app-secret',
            'base_url' => 'https://openapi.99food.com',
            'status' => 'active',
            'metadata' => json_encode(['seed' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Resolve ou cria loja de sandbox.
     */
    private function upsertShop(string $connection, string $appShopId, int $credentialId): int
    {
        $table = DB::connection($connection)->table('food99_shops');
        $columns = Schema::connection($connection)->getColumnListing('food99_shops');
        $columnSet = array_flip($columns);

        $payload = [
            'app_shop_id' => $appShopId,
        ];

        if (isset($columnSet['food99_app_credential_id'])) {
            $payload['food99_app_credential_id'] = $credentialId;
        }
        if (isset($columnSet['id_cadastro'])) {
            $payload['id_cadastro'] = 1;
        }
        if (isset($columnSet['food99_shop_id'])) {
            $payload['food99_shop_id'] = 'shop-sandbox-002';
        }
        if (isset($columnSet['name'])) {
            $payload['name'] = 'Loja Sandbox 002';
        }
        if (isset($columnSet['binding_status'])) {
            $payload['binding_status'] = 'bound';
        }
        if (isset($columnSet['auth_status'])) {
            $payload['auth_status'] = 'active';
        }
        if (isset($columnSet['last_synced_at'])) {
            $payload['last_synced_at'] = now();
        }
        if (isset($columnSet['metadata'])) {
            $payload['metadata'] = json_encode(['seed' => true], JSON_UNESCAPED_UNICODE);
        }
        if (isset($columnSet['updated_at'])) {
            $payload['updated_at'] = now();
        }

        $exists = $table->where('app_shop_id', $appShopId)->first();
        if (! is_object($exists)) {
            if (isset($columnSet['created_at'])) {
                $payload['created_at'] = now();
            }
            $table->insert($payload);
        } else {
            $table->where('app_shop_id', $appShopId)->update($payload);
        }

        $shop = $table->where('app_shop_id', $appShopId)->first();
        if (! is_object($shop) || ! is_numeric($shop->id ?? null)) {
            throw new RuntimeException('Nao foi possivel resolver food99_shops.id para o seed.');
        }

        return (int) $shop->id;
    }

    /**
     * Resolve ou cria token local de exemplo.
     */
    private function upsertToken(string $connection, string $appShopId, int $shopId): void
    {
        if (! Schema::connection($connection)->hasTable('food99_shop_tokens')) {
            return;
        }

        $table = DB::connection($connection)->table('food99_shop_tokens');
        $columns = Schema::connection($connection)->getColumnListing('food99_shop_tokens');
        $columnSet = array_flip($columns);

        $payload = [
            'app_shop_id' => $appShopId,
        ];

        if (isset($columnSet['food99_shop_id'])) {
            $payload['food99_shop_id'] = $shopId;
        }
        if (isset($columnSet['auth_token'])) {
            $payload['auth_token'] = 'sample-token-preview-wc-sandbox-002';
        }
        if (isset($columnSet['expires_at'])) {
            $payload['expires_at'] = now()->addDay();
        }
        if (isset($columnSet['last_refreshed_at'])) {
            $payload['last_refreshed_at'] = now();
        }
        if (isset($columnSet['last_retrieved_at'])) {
            $payload['last_retrieved_at'] = now();
        }
        if (isset($columnSet['payload'])) {
            $payload['payload'] = json_encode(['seed' => true], JSON_UNESCAPED_UNICODE);
        }
        if (isset($columnSet['updated_at'])) {
            $payload['updated_at'] = now();
        }
        if (isset($columnSet['created_at'])) {
            $payload['created_at'] = now();
        }

        $table->updateOrInsert(
            ['app_shop_id' => $appShopId],
            $payload,
        );
    }

    /**
     * Resolve ou cria menu padrao.
     */
    private function upsertMenu(string $connection, int $shopId): int
    {
        $table = DB::connection($connection)->table('food99_shop_menus');
        $appMenuId = 'menu_principal';

        $table->updateOrInsert(
            [
                'food99_shop_id' => $shopId,
                'app_menu_id' => $appMenuId,
            ],
            [
                'menu_name' => 'Menu Principal',
                'sort_order' => 1,
                'is_active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $menu = $table
            ->where('food99_shop_id', $shopId)
            ->where('app_menu_id', $appMenuId)
            ->first();

        if (! is_object($menu) || ! is_numeric($menu->id ?? null)) {
            throw new RuntimeException('Nao foi possivel resolver food99_shop_menus.id para o seed.');
        }

        return (int) $menu->id;
    }

    /**
     * Resolve ou cria categoria padrao.
     */
    private function upsertCategory(string $connection, int $shopId, int $menuId): int
    {
        $table = DB::connection($connection)->table('food99_shop_categories');
        $appCategoryId = 'burgers';

        $table->updateOrInsert(
            [
                'food99_shop_id' => $shopId,
                'app_category_id' => $appCategoryId,
            ],
            [
                'food99_shop_menu_id' => $menuId,
                'category_name' => 'Hamburgueres',
                'sort_order' => 1,
                'is_active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $category = $table
            ->where('food99_shop_id', $shopId)
            ->where('app_category_id', $appCategoryId)
            ->first();

        if (! is_object($category) || ! is_numeric($category->id ?? null)) {
            throw new RuntimeException('Nao foi possivel resolver food99_shop_categories.id para o seed.');
        }

        return (int) $category->id;
    }

    /**
     * Resolve ou cria itens padrao.
     *
     * @return array<int, int>
     */
    private function upsertItems(string $connection, int $shopId, int $categoryId): array
    {
        $table = DB::connection($connection)->table('food99_shop_items');
        $columns = Schema::connection($connection)->getColumnListing('food99_shop_items');
        $columnSet = array_flip($columns);
        $sampleAppItemIds = ['burger_classico', 'burger_bacon'];

        $first = [
            'food99_shop_id' => $shopId,
            'app_item_id' => 'burger_classico',
        ];
        $firstData = [
            'food99_shop_category_id' => $categoryId,
            'id_cadastro' => 1,
            'id_produto' => 1001,
            'id_grade' => 1001,
            'item_name' => 'Hamburguer Classico',
            'short_desc' => 'Pao, carne e queijo',
            'price_source' => 'grade',
            'price_amount' => 29.90,
            'price_cents' => 2990,
            'is_active' => 1,
            'publish_status' => 'draft',
            'updated_at' => now(),
            'created_at' => now(),
        ];

        $second = [
            'food99_shop_id' => $shopId,
            'app_item_id' => 'burger_bacon',
        ];
        $secondData = [
            'food99_shop_category_id' => $categoryId,
            'id_cadastro' => 1,
            'id_produto' => 1002,
            'id_grade' => 1002,
            'item_name' => 'Hamburguer Bacon',
            'short_desc' => 'Pao, carne, queijo e bacon',
            'price_source' => 'grade',
            'price_amount' => 34.90,
            'price_cents' => 3490,
            'is_active' => 1,
            'publish_status' => 'draft',
            'updated_at' => now(),
            'created_at' => now(),
        ];

        $first = $this->filterByExistingColumns($first, $columnSet);
        $firstData = $this->filterByExistingColumns($firstData, $columnSet);
        $second = $this->filterByExistingColumns($second, $columnSet);
        $secondData = $this->filterByExistingColumns($secondData, $columnSet);

        // Torna o seed idempotente mesmo apos execucoes parciais com erro.
        if (isset($columnSet['food99_shop_id'], $columnSet['app_item_id'])) {
            $table->where('food99_shop_id', $shopId)
                ->whereIn('app_item_id', $sampleAppItemIds)
                ->delete();
        }

        $table->insert(array_merge($first, $firstData));
        $table->insert(array_merge($second, $secondData));

        $rows = $table
            ->where('food99_shop_id', $shopId)
            ->whereIn('app_item_id', $sampleAppItemIds)
            ->get();

        $resolvedIds = [];
        foreach ($rows as $row) {
            $rowId = $this->resolveRowId($row);
            if ($rowId !== null) {
                $resolvedIds[] = $rowId;
            }
        }

        $resolvedIds = array_values(array_unique($resolvedIds));

        if ($resolvedIds === []) {
            $debug = [
                'columns' => $columns,
                'rows' => $rows->map(static fn ($row): array => (array) $row)->all(),
            ];

            throw new RuntimeException(
                'Nao foi possivel resolver IDs de food99_shop_items para o seed. debug='.
                json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }

        return $resolvedIds;
    }

    /**
     * Sincroniza vinculacao categoria-itens.
     *
     * @param  array<int, int>  $itemIds  IDs internos dos itens
     */
    private function syncCategoryItems(string $connection, int $categoryId, array $itemIds): void
    {
        $table = DB::connection($connection)->table('food99_shop_category_items');
        $columns = Schema::connection($connection)->getColumnListing('food99_shop_category_items');
        $columnSet = array_flip($columns);

        if (! isset($columnSet['food99_shop_category_id'], $columnSet['food99_shop_item_id'])) {
            throw new RuntimeException(
                'Tabela food99_shop_category_items sem colunas esperadas food99_shop_category_id/food99_shop_item_id.',
            );
        }

        $table->where('food99_shop_category_id', $categoryId)->delete();

        foreach ($itemIds as $index => $itemId) {
            $payload = [
                'food99_shop_category_id' => $categoryId,
                'food99_shop_item_id' => $itemId,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $table->insert($this->filterByExistingColumns($payload, $columnSet));
        }
    }

    /**
     * Mantem apenas campos existentes no schema da tabela.
     *
     * @param  array<string, mixed>  $payload  Payload original
     * @param  array<string, int|string>  $columnSet  Colunas indexadas por nome
     * @return array<string, mixed> Payload filtrado
     */
    private function filterByExistingColumns(array $payload, array $columnSet): array
    {
        $filtered = [];

        foreach ($payload as $column => $value) {
            if (isset($columnSet[$column])) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Resolve ID numerico de um registro independente do nome da coluna PK.
     *
     * @param  object|null  $row  Registro retornado pelo query builder
     * @return int|null ID resolvido ou null
     */
    private function resolveRowId(?object $row): ?int
    {
        if (! is_object($row)) {
            return null;
        }

        $candidates = ['id', 'id_item', 'id_shop_item', 'food99_shop_item_id'];
        foreach ($candidates as $candidate) {
            $value = data_get($row, $candidate);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
