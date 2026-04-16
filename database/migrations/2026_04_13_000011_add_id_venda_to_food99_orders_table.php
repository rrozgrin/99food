<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_marketplace')->hasTable('food99_orders')) {
            return;
        }

        Schema::connection('mysql_marketplace')->table('food99_orders', function (Blueprint $table): void {
            if (! Schema::connection('mysql_marketplace')->hasColumn('food99_orders', 'id_venda')) {
                $table->unsignedBigInteger('id_venda')->nullable()->after('sync_status');
                $table->index('id_venda', 'idx_food99_orders_id_venda');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('mysql_marketplace')->hasTable('food99_orders')) {
            return;
        }

        if (! Schema::connection('mysql_marketplace')->hasColumn('food99_orders', 'id_venda')) {
            return;
        }

        Schema::connection('mysql_marketplace')->table('food99_orders', function (Blueprint $table): void {
            $table->dropIndex('idx_food99_orders_id_venda');
            $table->dropColumn('id_venda');
        });
    }
};

