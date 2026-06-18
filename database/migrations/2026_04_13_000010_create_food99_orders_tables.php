<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_orders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_app_credential_id');
            $table->unsignedBigInteger('food99_shop_id');
            $table->unsignedBigInteger('food99_webhook_inbound_log_id')->nullable();
            $table->string('event_type', 50)->default('orderNew');
            $table->string('app_shop_id', 255);
            $table->string('order_id', 64);
            $table->integer('status')->nullable();
            $table->unsignedInteger('order_index')->nullable();
            $table->text('remark')->nullable();
            $table->string('country', 10)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->unsignedSmallInteger('pay_type')->nullable();
            $table->unsignedSmallInteger('delivery_type')->nullable();
            $table->integer('order_price')->nullable();
            $table->integer('real_price')->nullable();
            $table->integer('real_pay_price')->nullable();
            $table->integer('refund_price')->nullable();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->timestamp('create_time')->nullable();
            $table->timestamp('pay_time')->nullable();
            $table->timestamp('complete_time')->nullable();
            $table->timestamp('cancel_time')->nullable();
            $table->string('sync_status', 30)->default('new_order');
            $table->unsignedBigInteger('id_venda')->nullable();
            $table->string('erp_sale_id', 64)->nullable();
            $table->timestamp('erp_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['food99_shop_id', 'order_id'], 'uq_food99_orders_shop_order');
            $table->index(['sync_status', 'created_at'], 'idx_food99_orders_sync_created');
            $table->index('food99_webhook_inbound_log_id', 'idx_food99_orders_webhook_log');
            $table->index('id_venda', 'idx_food99_orders_id_venda');

            $table->foreign('food99_app_credential_id', 'fk_food99_orders_credential')
                ->references('id')
                ->on('food99_app_credentials')
                ->cascadeOnDelete();

            $table->foreign('food99_shop_id', 'fk_food99_orders_shop')
                ->references('id')
                ->on('food99_shops')
                ->cascadeOnDelete();

            $table->foreign('food99_webhook_inbound_log_id', 'fk_food99_orders_webhook_log')
                ->references('id')
                ->on('food99_webhook_inbound_logs')
                ->nullOnDelete();
        });

        Schema::connection('mysql_marketplace')->create('food99_order_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_order_id');
            $table->string('app_item_id', 120)->nullable();
            $table->string('app_external_id', 255)->nullable();
            $table->string('item_name', 255)->nullable();
            $table->unsignedInteger('amount')->default(1);
            $table->integer('sku_price')->nullable();
            $table->integer('total_price')->nullable();
            $table->integer('real_price')->nullable();
            $table->text('remark')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('food99_order_id', 'idx_food99_order_items_order_id');

            $table->foreign('food99_order_id', 'fk_food99_order_items_order')
                ->references('id')
                ->on('food99_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_order_items');
        Schema::connection('mysql_marketplace')->dropIfExists('food99_orders');
    }
};
