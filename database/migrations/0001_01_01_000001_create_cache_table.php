<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection('mysql_marketplace')->hasTable('cache')) {
            Schema::connection('mysql_marketplace')->create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
        }

        if (! Schema::connection('mysql_marketplace')->hasTable('cache_locks')) {
            Schema::connection('mysql_marketplace')->create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('cache');
        Schema::connection('mysql_marketplace')->dropIfExists('cache_locks');
    }
};
