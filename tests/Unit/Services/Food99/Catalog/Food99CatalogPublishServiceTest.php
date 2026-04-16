<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Food99\Catalog;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Exceptions\ApiException;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Food99\Catalog\Food99CatalogPayloadService;
use App\Services\Food99\Catalog\Food99CatalogPublishService;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99PublishJobRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;

class Food99CatalogPublishServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.food99.base_url' => 'https://food99.test',
            'services.food99.app_id' => 'app-test',
            'services.food99.app_secret' => 'secret-test',
            'services.food99.timeout' => 10,
        ]);
    }

    #[Test]
    public function deve_lancar_excecao_quando_token_local_nao_for_encontrado(): void
    {
        $payloadService = Mockery::mock(Food99CatalogPayloadService::class);
        $payloadService->shouldReceive('buildUploadPayloadPreview')
            ->once()
            ->with('shop_001', [])
            ->andReturn([
                'food99_shop_id' => 10,
                'token_found' => false,
                'payload' => [],
                'stats' => ['menus' => 0, 'categories' => 0, 'items' => 0],
            ]);

        $publishJobRepository = Mockery::mock(Food99PublishJobRepositoryInterface::class);
        $publishJobRepository->shouldReceive('create')->never();

        $service = new Food99CatalogPublishService(
            $payloadService,
            $publishJobRepository,
            Mockery::mock(UsuarioLogadoService::class),
            Mockery::mock(Food99ShopRepositoryInterface::class),
            Mockery::mock(Food99ShopItemRepositoryInterface::class),
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(422);

        $service->publishCatalog('shop_001', 7);
    }

    #[Test]
    public function deve_registrar_job_como_sucesso_quando_upload_for_concluido(): void
    {
        Http::fake([
            'https://food99.test/v1/item/item/upload' => Http::response([
                'errno' => 0,
                'data' => ['task_id' => 'task-123'],
            ], 200),
        ]);

        $payloadService = Mockery::mock(Food99CatalogPayloadService::class);
        $payloadService->shouldReceive('buildUploadPayloadPreview')
            ->once()
            ->with('shop_002', [])
            ->andReturn([
                'food99_shop_id' => 20,
                'token_found' => true,
                'payload' => [
                    'menus' => [],
                    'categories' => [],
                    'items' => [],
                    'auth_token' => 'token-x',
                ],
                'stats' => ['menus' => 0, 'categories' => 0, 'items' => 0],
            ]);

        $publishJobRepository = Mockery::mock(Food99PublishJobRepositoryInterface::class);
        $publishJobRepository->shouldReceive('create')
            ->once()
            ->andReturn((object) ['id' => 88]);

        $publishJobRepository->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(static fn (array $data): bool => ($data['status'] ?? null) === 'success'
                    && isset($data['response_payload'])),
                88,
            )
            ->andReturn(true);

        $shopItemRepository = Mockery::mock(Food99ShopItemRepositoryInterface::class);
        $shopItemRepository->shouldReceive('markPublishedByShopAndAppItemIds')
            ->once()
            ->with(20, [], [])
            ->andReturnNull();

        $service = new Food99CatalogPublishService(
            $payloadService,
            $publishJobRepository,
            Mockery::mock(UsuarioLogadoService::class),
            Mockery::mock(Food99ShopRepositoryInterface::class),
            $shopItemRepository,
        );
        $response = $service->publishCatalog('shop_002', 15);

        $this->assertSame('success', $response['status']);
        $this->assertSame(88, $response['job_id']);
        $this->assertSame('shop_002', $response['app_shop_id']);
        $this->assertSame(0, $response['published_items']);
    }

    #[Test]
    public function deve_registrar_job_como_falha_quando_api_externa_retornar_erro_http(): void
    {
        Http::fake([
            'https://food99.test/v1/item/item/upload' => Http::response([
                'msg' => 'falha na API externa',
            ], 502),
        ]);

        $payloadService = Mockery::mock(Food99CatalogPayloadService::class);
        $payloadService->shouldReceive('buildUploadPayloadPreview')
            ->once()
            ->with('shop_003', [])
            ->andReturn([
                'food99_shop_id' => 30,
                'token_found' => true,
                'payload' => [
                    'menus' => [],
                    'categories' => [],
                    'items' => [],
                    'auth_token' => 'token-y',
                ],
                'stats' => ['menus' => 0, 'categories' => 0, 'items' => 0],
            ]);

        $publishJobRepository = Mockery::mock(Food99PublishJobRepositoryInterface::class);
        $publishJobRepository->shouldReceive('create')
            ->once()
            ->andReturn((object) ['id' => 89]);

        $publishJobRepository->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(static fn (array $data): bool => ($data['status'] ?? null) === 'failed'
                    && str_contains((string) ($data['error_message'] ?? ''), 'falha na API externa')),
                89,
            )
            ->andReturn(true);

        $shopItemRepository = Mockery::mock(Food99ShopItemRepositoryInterface::class);
        $shopItemRepository->shouldReceive('markFailedByShopAndAppItemIds')
            ->once()
            ->with(30, [], Mockery::type('string'))
            ->andReturnNull();

        $service = new Food99CatalogPublishService(
            $payloadService,
            $publishJobRepository,
            Mockery::mock(UsuarioLogadoService::class),
            Mockery::mock(Food99ShopRepositoryInterface::class),
            $shopItemRepository,
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(502);

        $service->publishCatalog('shop_003', 20);
    }
}
