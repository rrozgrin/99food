<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Food99\Webhook;

use App\Exceptions\ApiException;
use App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Webhook\Food99WebhookInboundLogRepositoryInterface;
use App\Services\Food99\Orders\Food99OrderErpSyncService;
use App\Services\Food99\Webhook\Food99WebhookService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Food99WebhookServiceIdempotencyTest extends TestCase
{
    #[Test]
    public function assinatura_ausente_e_rejeitada(): void
    {
        config(['services.food99.app_secret' => 'test-secret']);

        $appCredentialRepository = Mockery::mock(Food99AppCredentialRepositoryInterface::class);
        $shopRepository = Mockery::mock(Food99ShopRepositoryInterface::class);
        $webhookLogRepository = Mockery::mock(Food99WebhookInboundLogRepositoryInterface::class);

        $appCredentialRepository
            ->shouldReceive('findLatestByAppId')
            ->twice()
            ->with('123')
            ->andReturn((object) ['id' => 2]);

        $shopRepository
            ->shouldReceive('findByCredentialAndAppShopId')
            ->once()
            ->with(2, 'shop-1')
            ->andReturn((object) ['id' => 1]);

        $webhookLogRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn((object) ['id' => 10]);

        $webhookLogRepository
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::on(static fn (array $attributes): bool => $attributes['status'] === 'failed'), 10)
            ->andReturnTrue();

        $service = new Food99WebhookService(
            $appCredentialRepository,
            $shopRepository,
            $webhookLogRepository,
            Mockery::mock(Food99OrderRepositoryInterface::class),
            Mockery::mock(Food99OrderErpSyncService::class),
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(401);

        $service->handle([
            'app_id' => 123,
            'app_shop_id' => 'shop-1',
            'type' => 'orderNew',
            'timestamp' => 1776185616,
        ], '{}');
    }

    #[Test]
    public function replay_de_order_new_deve_ser_tolerado_sem_quebrar_fluxo(): void
    {
        config([
            'services.food99.app_secret' => 'test-secret',
            'services.food99.order_new_sync_mode' => 'sync',
        ]);

        $appCredentialRepository = Mockery::mock(Food99AppCredentialRepositoryInterface::class);
        $shopRepository = Mockery::mock(Food99ShopRepositoryInterface::class);
        $webhookLogRepository = Mockery::mock(Food99WebhookInboundLogRepositoryInterface::class);
        $orderRepository = Mockery::mock(Food99OrderRepositoryInterface::class);
        $orderErpSyncService = Mockery::mock(Food99OrderErpSyncService::class);

        $appCredentialRepository
            ->shouldReceive('findLatestByAppId')
            ->times(8)
            ->with('5764607788456609652')
            ->andReturn((object) ['id' => 2]);

        $shopRepository
            ->shouldReceive('findByCredentialAndAppShopId')
            ->times(4)
            ->with(2, 'wc-sandbox-002')
            ->andReturn((object) ['id' => 1]);

        $webhookLogRepository
            ->shouldReceive('create')
            ->twice()
            ->andReturn((object) ['id' => 10], (object) ['id' => 11]);

        $webhookLogRepository
            ->shouldReceive('update')
            ->twice()
            ->andReturn(true);

        $orderRepository
            ->shouldReceive('upsertOrderWithItems')
            ->twice()
            ->with(
                Mockery::on(static fn (array $attributes): bool => ($attributes['food99_shop_id'] ?? null) === 1
                    && ($attributes['order_id'] ?? null) === '5764607775769495278'),
                Mockery::on(static fn (array $values): bool => ($values['event_type'] ?? null) === 'orderNew'
                    && ($values['sync_status'] ?? null) === 'new_order'),
                Mockery::type('array'),
            )
            ->andReturn(55, 55);

        $orderErpSyncService
            ->shouldReceive('syncOrderById')
            ->never();

        $service = new Food99WebhookService(
            $appCredentialRepository,
            $shopRepository,
            $webhookLogRepository,
            $orderRepository,
            $orderErpSyncService,
        );

        $payload = [
            'app_id' => 5764607788456609652,
            'app_shop_id' => 'wc-sandbox-002',
            'type' => 'orderNew',
            'timestamp' => 1776185616,
            'data' => [
                'order_id' => 5764607775769495278,
                'order_info' => [
                    'order_id' => 5764607775769495278,
                    'status' => 100,
                    'order_index' => 1,
                    'country' => 'BR',
                    'timezone' => 'America/Sao_Paulo',
                    'pay_type' => 1,
                    'delivery_type' => 1,
                    'price' => [
                        'order_price' => 2299,
                        'real_price' => 2299,
                        'real_pay_price' => 2299,
                        'refund_price' => 0,
                    ],
                    'receive_address' => [
                        'name' => 'DiDi',
                        'phone' => null,
                    ],
                    'create_time' => 1776185616,
                    'pay_time' => 1776185616,
                    'order_items' => [
                        [
                            'app_item_id' => 'p1017855840_g1028102882',
                            'name' => 'Hamburguer Cheddar',
                            'amount' => 1,
                            'sku_price' => 2299,
                            'total_price' => 2299,
                            'real_price' => 2299,
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $signature = md5($rawBody.'test-secret');

        $service->handle($payload, $rawBody, $signature);
        $service->handle($payload, $rawBody, $signature);

        $this->assertTrue(true);
    }

    #[Test]
    public function replay_de_order_finish_deve_ser_idempotente_no_update_de_status(): void
    {
        config([
            'services.food99.app_secret' => 'test-secret',
        ]);

        $appCredentialRepository = Mockery::mock(Food99AppCredentialRepositoryInterface::class);
        $shopRepository = Mockery::mock(Food99ShopRepositoryInterface::class);
        $webhookLogRepository = Mockery::mock(Food99WebhookInboundLogRepositoryInterface::class);
        $orderRepository = Mockery::mock(Food99OrderRepositoryInterface::class);
        $orderErpSyncService = Mockery::mock(Food99OrderErpSyncService::class);

        $appCredentialRepository
            ->shouldReceive('findLatestByAppId')
            ->times(8)
            ->with('5764607788456609652')
            ->andReturn((object) ['id' => 2]);

        $shopRepository
            ->shouldReceive('findByCredentialAndAppShopId')
            ->times(4)
            ->with(2, 'wc-sandbox-002')
            ->andReturn((object) ['id' => 1]);

        $webhookLogRepository
            ->shouldReceive('create')
            ->twice()
            ->andReturn((object) ['id' => 21], (object) ['id' => 22]);

        $webhookLogRepository
            ->shouldReceive('update')
            ->twice()
            ->andReturn(true);

        $orderRepository
            ->shouldReceive('updateOrCreate')
            ->twice()
            ->with(
                ['food99_shop_id' => 1, 'order_id' => '5764607523553412691'],
                Mockery::on(static fn (array $values): bool => ($values['event_type'] ?? null) === 'orderFinish'
                    && ($values['sync_status'] ?? null) === 'pending_sync'),
            )
            ->andReturn((object) ['id' => 99]);

        $orderErpSyncService
            ->shouldReceive('markOrderFinished')
            ->twice()
            ->with(1, '5764607523553412691')
            ->andReturnNull();

        $service = new Food99WebhookService(
            $appCredentialRepository,
            $shopRepository,
            $webhookLogRepository,
            $orderRepository,
            $orderErpSyncService,
        );

        $payload = [
            'app_id' => 5764607788456609652,
            'app_shop_id' => 'wc-sandbox-002',
            'type' => 'orderFinish',
            'timestamp' => 1776172417,
            'data' => [
                'order_id' => 5764607523553412691,
            ],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $signature = md5($rawBody.'test-secret');

        $service->handle($payload, $rawBody, $signature);
        $service->handle($payload, $rawBody, $signature);

        $this->assertTrue(true);
    }
}
