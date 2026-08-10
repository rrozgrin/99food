<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.mysql', config('database.connections.sqlite'));
        config()->set('jwt.secret', str_repeat('a', 32));
        DB::purge('mysql');

        Schema::connection('mysql')->create('webc_usuario', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cadastro')->nullable();
            $table->string('login', 20)->unique();
            $table->string('senha', 255)->nullable();
            $table->string('ativo', 1);
            $table->timestamp('data_criacao')->nullable();
            $table->dateTime('data_alteracao')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('mysql')->dropIfExists('webc_usuario');
        DB::purge('mysql');

        parent::tearDown();
    }

    #[Test]
    public function autentica_usuario_ativo_com_hash_bcrypt_compativel_com_o_erp(): void
    {
        $this->createErpUser(login: 'admin', senha: Hash::make('senha-segura'));

        $response = $this->postJson('/api/v1/login', [
            'login' => 'admin',
            'senha' => 'senha-segura',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('expires_in', 3600)
            ->assertJsonStructure(['access_token']);
    }

    #[Test]
    public function rejeita_senha_incorreta_usuario_inativo_e_hash_incompativel(): void
    {
        $this->createErpUser(login: 'senha-incorreta', senha: Hash::make('senha-correta'));
        $this->createErpUser(login: 'inativo', senha: Hash::make('senha-correta'), ativo: 'I');
        $this->createErpUser(login: 'hash-legado', senha: '123456');

        foreach ([
            ['login' => 'senha-incorreta', 'senha' => 'errada'],
            ['login' => 'inativo', 'senha' => 'senha-correta'],
            ['login' => 'hash-legado', 'senha' => '123456'],
            ['login' => 'inexistente', 'senha' => 'senha-correta'],
        ] as $credentials) {
            $this->postJson('/api/v1/login', $credentials)
                ->assertUnauthorized()
                ->assertExactJson(['error' => 'Login inválido']);
        }
    }

    #[Test]
    public function valida_login_e_senha_antes_de_autenticar(): void
    {
        $this->postJson('/api/v1/login', ['login' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('senha');

        $this->postJson('/api/v1/login', ['senha' => 'senha-segura'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');
    }

    #[Test]
    public function token_emitido_permite_consultar_usuario_sem_expor_senha(): void
    {
        $this->createErpUser(login: 'admin', senha: Hash::make('senha-segura'));

        $token = $this->postJson('/api/v1/login', [
            'login' => 'admin',
            'senha' => 'senha-segura',
        ])->json('access_token');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonMissingPath('conteudo.senha');
    }

    #[Test]
    public function limita_tentativas_de_login_por_ip_e_login(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/login', [
                'login' => 'admin',
                'senha' => 'senha-incorreta',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'login' => 'admin',
            'senha' => 'senha-incorreta',
        ])->assertTooManyRequests();
    }

    private function createErpUser(string $login, string $senha, string $ativo = 'A'): void
    {
        DB::connection('mysql')->table('webc_usuario')->insert([
            'id_cadastro' => 1,
            'login' => $login,
            'senha' => $senha,
            'ativo' => $ativo,
        ]);
    }
}
