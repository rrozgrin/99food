<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Popula o ambiente local com usuarios ERP e catalogo 99Food de sandbox.
     */
    public function run(): void
    {
        $erp = DB::connection('mysql');

        $erp->table('webc_usuario')->upsert([
            [
                'id_cadastro' => 1,
                'login' => 'admin',
                'senha' => Hash::make('senha-segura'),
                'ativo' => 'A',
                'data_criacao' => now(),
                'data_alteracao' => now(),
            ],
            [
                'id_cadastro' => 1,
                'login' => 'gerente',
                'senha' => Hash::make('senha-gerente'),
                'ativo' => 'A',
                'data_criacao' => now(),
                'data_alteracao' => now(),
            ],
        ], ['login'], ['id_cadastro', 'senha', 'ativo', 'data_alteracao']);

        $erpSchema = Schema::connection('mysql');

        if ($erpSchema->hasTable('webc_usuario_role') && $erpSchema->hasTable('webc_roles')) {
            $adminId = $erp->table('webc_usuario')->where('login', 'admin')->value('id');
            $adminRoleId = $erp->table('webc_roles')->where('nome', 'admin')->value('id');

            if ($adminId !== null && $adminRoleId !== null) {
                $erp->table('webc_usuario_role')->updateOrInsert([
                    'usuario_id' => $adminId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }

        $this->call(Food99CatalogSampleSeeder::class);

        if ((bool) config('services.food99.demo_mode', false)) {
            $this->call(Food99DemoSeeder::class);
        }
    }
}
