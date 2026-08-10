<?php

declare(strict_types=1);

namespace Tests\Feature\Food99\Auth;

use App\Http\Middleware\ApiJwtMiddleware;
use App\Services\Food99\Auth\Food99AuthService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Food99AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ApiJwtMiddleware::class);
    }

    #[Test]
    public function deve_validar_app_shop_id_no_endpoint_de_obter_token(): void
    {
        $response = $this->postJson('/api/v1/food99/auth/token/get', []);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 422);
        $this->assertIsArray($response->json('conteudo.app_shop_id'));
    }

    #[Test]
    public function deve_retornar_token_quando_servico_responde_com_sucesso(): void
    {
        $service = Mockery::mock(Food99AuthService::class);
        $service->shouldReceive('getTokenByStore')
            ->once()
            ->with('shop_123')
            ->andReturn([
                'app_shop_id' => 'shop_123',
                'token_found' => true,
                'token_expires_at' => '2026-12-06 10:13:20',
            ]);

        $this->app->instance(Food99AuthService::class, $service);

        $response = $this->postJson('/api/v1/food99/auth/token/get', [
            'app_shop_id' => 'shop_123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('conteudo.app_shop_id', 'shop_123');
        $response->assertJsonPath('conteudo.token_found', true);
        $response->assertJsonMissingPath('conteudo.auth_token');
    }

    #[Test]
    public function deve_retornar_404_quando_token_local_nao_for_encontrado(): void
    {
        $service = Mockery::mock(Food99AuthService::class);
        $service->shouldReceive('getStoredTokenByStore')
            ->once()
            ->with('shop_404')
            ->andReturn(null);

        $this->app->instance(Food99AuthService::class, $service);

        $response = $this->getJson('/api/v1/food99/auth/token/local/shop_404');

        $response->assertStatus(404);
        $response->assertJsonPath('code', 404);
        $response->assertJsonPath('conteudo', null);
        $response->assertJsonPath('msg', 'Token local nao encontrado para o app_shop_id informado.');
    }
}
