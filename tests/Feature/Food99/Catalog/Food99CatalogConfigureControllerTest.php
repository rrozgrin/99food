<?php

declare(strict_types=1);

namespace Tests\Feature\Food99\Catalog;

use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Http\Middleware\ApiJwtMiddleware;
use App\Services\Food99\Catalog\Food99CatalogManagementService;

class Food99CatalogConfigureControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ApiJwtMiddleware::class);
    }

    #[Test]
    public function deve_configurar_item_existente(): void
    {
        $service = Mockery::mock(Food99CatalogManagementService::class);
        $service->shouldReceive('configureItem')
            ->once()
            ->with(Mockery::on(static fn (array $payload): bool => $payload['app_shop_id'] === 'wc-sandbox-002'
                && $payload['app_item_id'] === 'p123_g456'
                && $payload['app_category_id'] === 'hamburgueres'))
            ->andReturn([
                'id' => 10,
                'app_item_id' => 'p123_g456',
                'food99_shop_category_id' => 22,
            ]);

        $this->app->instance(Food99CatalogManagementService::class, $service);

        $response = $this->postJson('/api/v1/food99/catalog/items/configure', [
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_id' => 'p123_g456',
            'app_category_id' => 'hamburgueres',
        ]);

        $response->assertOk();
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('conteudo.app_item_id', 'p123_g456');
    }

    #[Test]
    public function deve_validar_campos_obrigatorios_na_configuracao(): void
    {
        $response = $this->postJson('/api/v1/food99/catalog/items/configure', [
            'app_shop_id' => 'wc-sandbox-002',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 422);
        $this->assertIsArray($response->json('conteudo.app_item_id'));
        $this->assertIsArray($response->json('conteudo.app_category_id'));
    }
}
