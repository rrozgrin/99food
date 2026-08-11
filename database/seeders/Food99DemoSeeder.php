<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Food99DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedErpProducts();
        $this->seedPendingOrder();

        if ($this->command !== null) {
            $this->command->info('Food99DemoSeeder executado com sucesso.');
            $this->command->line('login: admin / senha-segura');
            $this->command->line('app_shop_id: wc-sandbox-002');
            $this->command->line('pedido demo: DEMO-ORDER-001');
        }
    }

    private function seedErpProducts(): void
    {
        $schema = Schema::connection('mysql');
        if (! $schema->hasTable('produto') || ! $schema->hasTable('grade')) {
            return;
        }

        $erp = DB::connection('mysql');

        $products = [
            [
                'id' => 1001,
                'descricao' => 'Hamburguer Classico',
                'codigo_barra' => '7899900001001',
                'identificacao_interna' => 'DEMO-BURGER-CLASSICO',
                'price' => 29.90,
            ],
            [
                'id' => 1002,
                'descricao' => 'Hamburguer Bacon',
                'codigo_barra' => '7899900001002',
                'identificacao_interna' => 'DEMO-BURGER-BACON',
                'price' => 34.90,
            ],
        ];

        foreach ($products as $product) {
            $erp->table('produto')->updateOrInsert(
                ['id' => $product['id']],
                [
                    'descricao' => $product['descricao'],
                    'id_cadastro' => 1,
                    'id_usuario' => 1,
                    'data_cadastro' => now(),
                    'ativo' => 1,
                    'codigo_barra' => $product['codigo_barra'],
                    'barra' => $product['codigo_barra'],
                    'ean' => $product['codigo_barra'],
                    'identificacao_interna' => $product['identificacao_interna'],
                    'custo' => $product['price'],
                    'custo_medio_venda' => $product['price'],
                    'custo_medio_venda_atacado' => $product['price'],
                    'qtd_minima' => 0,
                    'locacao_quantidade' => 0,
                ],
            );

            $erp->table('grade')->updateOrInsert(
                ['id_grade' => $product['id']],
                [
                    'id_cadastro' => 1,
                    'id_produto' => $product['id'],
                    'codigo_barra' => $product['codigo_barra'],
                    'codigo_interno' => $product['identificacao_interna'],
                    'valor_custo' => $product['price'],
                    'valor_varejo_aprazo' => $product['price'],
                    'ativo' => 'S',
                ],
            );
        }
    }

    private function seedPendingOrder(): void
    {
        $connection = 'mysql_marketplace';
        $schema = Schema::connection($connection);
        if (! $schema->hasTable('food99_orders') || ! $schema->hasTable('food99_order_items')) {
            return;
        }

        $marketplace = DB::connection($connection);
        $shop = $marketplace->table('food99_shops')->where('app_shop_id', 'wc-sandbox-002')->first();
        $credentialId = $marketplace->table('food99_app_credentials')->value('id');

        if (! is_object($shop) || ! is_numeric($shop->id ?? null) || ! is_numeric($credentialId)) {
            return;
        }

        $orderId = 'DEMO-ORDER-001';

        $marketplace->table('food99_orders')->updateOrInsert(
            [
                'food99_shop_id' => (int) $shop->id,
                'order_id' => $orderId,
            ],
            [
                'food99_app_credential_id' => (int) $credentialId,
                'food99_webhook_inbound_log_id' => null,
                'event_type' => 'orderFinish',
                'app_shop_id' => 'wc-sandbox-002',
                'status' => 600,
                'order_index' => 1,
                'remark' => 'Pedido demo para portfolio',
                'country' => 'BR',
                'timezone' => 'America/Sao_Paulo',
                'pay_type' => 210,
                'delivery_type' => 1,
                'order_price' => 6480,
                'real_price' => 6480,
                'real_pay_price' => 6480,
                'refund_price' => 0,
                'customer_name' => 'Cliente Demo Food99',
                'customer_phone' => '11999990000',
                'create_time' => now()->subMinutes(15),
                'pay_time' => now()->subMinutes(14),
                'complete_time' => null,
                'cancel_time' => null,
                'sync_status' => 'pending_sync',
                'id_venda' => null,
                'erp_sale_id' => null,
                'erp_synced_at' => null,
                'error_message' => null,
                'payload' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $order = $marketplace->table('food99_orders')
            ->where('food99_shop_id', (int) $shop->id)
            ->where('order_id', $orderId)
            ->first();

        if (! is_object($order) || ! is_numeric($order->id ?? null)) {
            return;
        }

        $marketplace->table('food99_order_items')->where('food99_order_id', (int) $order->id)->delete();
        $marketplace->table('food99_order_items')->insert([
            [
                'food99_order_id' => (int) $order->id,
                'app_item_id' => 'burger_classico',
                'app_external_id' => 'burger_classico',
                'item_name' => 'Hamburguer Classico',
                'amount' => 1,
                'sku_price' => 2990,
                'total_price' => 2990,
                'real_price' => 2990,
                'remark' => null,
                'payload' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'food99_order_id' => (int) $order->id,
                'app_item_id' => 'burger_bacon',
                'app_external_id' => 'burger_bacon',
                'item_name' => 'Hamburguer Bacon',
                'amount' => 1,
                'sku_price' => 3490,
                'total_price' => 3490,
                'real_price' => 3490,
                'remark' => null,
                'payload' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
