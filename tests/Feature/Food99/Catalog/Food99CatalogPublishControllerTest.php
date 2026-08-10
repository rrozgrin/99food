<?php

declare(strict_types=1);

namespace Tests\Feature\Food99\Catalog;

use App\Http\Middleware\ApiJwtMiddleware;
use App\Services\Food99\Catalog\Food99CatalogPublishService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Food99CatalogPublishControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ApiJwtMiddleware::class);
    }

    #[Test]
    public function deve_publicar_catalogo_e_retornar_payload_do_servico(): void
    {
        $service = Mockery::mock(Food99CatalogPublishService::class);
        $service->shouldReceive('publishCatalog')
            ->once()
            ->with('wc-sandbox-002', null, ['item-1', 'item-2'])
            ->andReturn([
                'app_shop_id' => 'wc-sandbox-002',
                'status' => 'success',
                'published_items' => 2,
            ]);

        $this->app->instance(Food99CatalogPublishService::class, $service);

        $response = $this->postJson('/api/v1/food99/catalog/publish', [
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_ids' => ['item-1', 'item-2'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('conteudo.status', 'success');
        $response->assertJsonPath('conteudo.published_items', 2);
    }

    #[Test]
    public function deve_validar_lista_de_itens_na_publicacao_seletiva(): void
    {
        $response = $this->postJson('/api/v1/food99/catalog/publish', [
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_ids' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 422);
        $this->assertIsArray($response->json('conteudo.app_item_ids'));
    }

    #[Test]
    public function deve_listar_jobs_de_publicacao(): void
    {
        $service = Mockery::mock(Food99CatalogPublishService::class);
        $service->shouldReceive('listJobs')
            ->once()
            ->with('wc-sandbox-002')
            ->andReturn([
                'app_shop_id' => 'wc-sandbox-002',
                'food99_shop_id' => 10,
                'jobs' => [
                    ['id' => 1, 'status' => 'success'],
                ],
            ]);

        $this->app->instance(Food99CatalogPublishService::class, $service);

        $response = $this->getJson('/api/v1/food99/catalog/publish/jobs/wc-sandbox-002');

        $response->assertOk();
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('conteudo.food99_shop_id', 10);
        $response->assertJsonPath('conteudo.jobs.0.status', 'success');
    }
}
