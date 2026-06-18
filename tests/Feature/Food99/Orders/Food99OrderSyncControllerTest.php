<?php

declare(strict_types=1);

namespace Tests\Feature\Food99\Orders;

use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Http\Middleware\ApiJwtMiddleware;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Food99\Orders\Food99OrderErpSyncService;

class Food99OrderSyncControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ApiJwtMiddleware::class);
    }

    #[Test]
    public function deve_retornar_envelope_padrao_quando_usuario_nao_tem_cadastro(): void
    {
        $usuarioLogado = Mockery::mock(UsuarioLogadoService::class);
        $usuarioLogado->shouldReceive('getIdCadastroLogado')
            ->once()
            ->andReturn(null);

        $service = Mockery::mock(Food99OrderErpSyncService::class);
        $service->shouldNotReceive('listSyncQueue');

        $this->app->instance(UsuarioLogadoService::class, $usuarioLogado);
        $this->app->instance(Food99OrderErpSyncService::class, $service);

        $response = $this->getJson('/api/v1/food99/orders/sync-queue');

        $response->assertStatus(403);
        $response->assertJsonPath('code', 403);
        $response->assertJsonPath('conteudo', null);
        $response->assertJsonPath('msg', 'id_cadastro nao encontrado para o usuario autenticado');
    }
}
