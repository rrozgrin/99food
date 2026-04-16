<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Food99\Orders;

use Carbon\Carbon;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Food99\Orders\Food99Order;
use App\Services\Food99\Orders\Food99OrderErpSyncService;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderItemRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\GradeRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\ClienteRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\ProdutoRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaItensRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaPagamentoRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaInformacoesRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\WebcUsuarioRepositoryInterface;

class Food99OrderErpSyncServiceIdempotencyTest extends TestCase
{
    #[Test]
    public function nao_deve_reprocessar_sync_quando_order_ja_foi_bloqueado_para_processamento(): void
    {
        [$service, $deps] = $this->makeService();

        $deps['orderRepository']
            ->shouldReceive('markAsProcessing')
            ->once()
            ->with(10)
            ->andReturn(null);

        $deps['orderRepository']
            ->shouldReceive('updateById')
            ->never();

        $service->syncOrderById(10);

        $this->assertTrue(true);
    }

    #[Test]
    public function deve_reaproveitar_venda_existente_sem_criar_duplicata_no_sync(): void
    {
        [, $deps] = $this->makeService();

        $service = Mockery::mock(
            Food99OrderErpSyncService::class,
            [
                $deps['shopRepository'],
                $deps['orderRepository'],
                $deps['orderItemRepository'],
                $deps['shopItemRepository'],
                $deps['clienteRepository'],
                $deps['produtoRepository'],
                $deps['gradeRepository'],
                $deps['vendaRepository'],
                $deps['vendaItensRepository'],
                $deps['vendaPagamentoRepository'],
                $deps['vendaInformacoesRepository'],
                $deps['webcUsuarioRepository'],
            ],
        )->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn ($callback) => $callback());

        $order = (object) [
            'id' => 15,
            'food99_shop_id' => 1,
            'order_id' => 'ORD-123',
            'pay_type' => 1,
            'pay_time' => Carbon::now(),
            'remark' => null,
        ];

        $deps['orderRepository']
            ->shouldReceive('markAsProcessing')
            ->once()
            ->with(15)
            ->andReturn($order);

        $deps['shopRepository']
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn((object) ['id' => 1, 'id_cadastro' => 73]);

        $deps['vendaRepository']
            ->shouldReceive('findIdByCadastroOrigemDescricao')
            ->once()
            ->with(73, 'B2W', 'FOOD99_ORDER:ORD-123|SHOP:1')
            ->andReturn(256467477);

        $deps['orderRepository']
            ->shouldReceive('updateById')
            ->once()
            ->with(
                15,
                Mockery::on(static function (array $data): bool {
                    return ($data['id_venda'] ?? null) === 256467477
                        && ($data['erp_sale_id'] ?? null) === '256467477'
                        && ($data['sync_status'] ?? null) === 'synced_erp'
                        && array_key_exists('erp_synced_at', $data)
                        && array_key_exists('error_message', $data)
                        && $data['error_message'] === null;
                }),
            )
            ->andReturn(true);

        $service->syncOrderById(15);

        $this->assertTrue(true);
    }

    #[Test]
    public function nao_deve_criar_venda_pagamento_duplicado_em_replay_de_order_finish(): void
    {
        [$service, $deps] = $this->makeService();

        $order = new Food99Order([
            'food99_shop_id' => 1,
            'order_id' => 'ORD-FIN-001',
            'id_venda' => 300,
            'real_pay_price' => 2299,
            'order_price' => 2299,
        ]);
        $order->id = 22;

        $deps['orderRepository']
            ->shouldReceive('findByShopIdAndOrderId')
            ->once()
            ->with(1, 'ORD-FIN-001')
            ->andReturn($order);

        $deps['shopRepository']
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn((object) ['id' => 1, 'id_cadastro' => 73]);

        $deps['vendaPagamentoRepository']
            ->shouldReceive('findOneBy')
            ->once()
            ->with([['id_venda', '=', 300]])
            ->andReturn((object) ['id' => 900]);

        $deps['vendaPagamentoRepository']
            ->shouldReceive('create')
            ->never();

        $deps['vendaRepository']
            ->shouldReceive('updateSituacaoById')
            ->once()
            ->with(300, 'C');

        $deps['orderRepository']
            ->shouldReceive('updateById')
            ->once()
            ->with(
                22,
                Mockery::on(static function (array $data): bool {
                    return ($data['sync_status'] ?? null) === 'finished_erp'
                        && ($data['id_venda'] ?? null) === 300
                        && ($data['erp_sale_id'] ?? null) === '300'
                        && array_key_exists('error_message', $data)
                        && $data['error_message'] === null;
                }),
            )
            ->andReturn(true);

        $service->markOrderFinished(1, 'ORD-FIN-001');

        $this->assertTrue(true);
    }

    #[Test]
    public function deve_fazer_fallback_no_order_finish_para_criar_venda_quando_ordem_veio_sem_vinculo(): void
    {
        [, $deps] = $this->makeService();

        $service = Mockery::mock(
            Food99OrderErpSyncService::class . '[syncOrderById]',
            [
                $deps['shopRepository'],
                $deps['orderRepository'],
                $deps['orderItemRepository'],
                $deps['shopItemRepository'],
                $deps['clienteRepository'],
                $deps['produtoRepository'],
                $deps['gradeRepository'],
                $deps['vendaRepository'],
                $deps['vendaItensRepository'],
                $deps['vendaPagamentoRepository'],
                $deps['vendaInformacoesRepository'],
                $deps['webcUsuarioRepository'],
            ],
        )->makePartial();

        $orderBefore = new Food99Order([
            'food99_shop_id' => 1,
            'order_id' => 'ORD-FALLBACK-001',
            'id_venda' => null,
            'real_pay_price' => 3490,
            'order_price' => 3490,
        ]);
        $orderBefore->id = 99;

        $orderAfter = new Food99Order([
            'food99_shop_id' => 1,
            'order_id' => 'ORD-FALLBACK-001',
            'id_venda' => 501,
            'real_pay_price' => 3490,
            'order_price' => 3490,
        ]);
        $orderAfter->id = 99;

        $deps['orderRepository']
            ->shouldReceive('findByShopIdAndOrderId')
            ->once()
            ->with(1, 'ORD-FALLBACK-001')
            ->andReturn($orderBefore);

        $deps['shopRepository']
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn((object) ['id' => 1, 'id_cadastro' => 73]);

        $deps['vendaRepository']
            ->shouldReceive('findIdByCadastroOrigemDescricao')
            ->once()
            ->with(73, 'B2W', 'FOOD99_ORDER:ORD-FALLBACK-001|SHOP:1')
            ->andReturn(null);

        $service
            ->shouldReceive('syncOrderById')
            ->once()
            ->with(99)
            ->andReturnNull();

        $deps['orderRepository']
            ->shouldReceive('find')
            ->once()
            ->with(99)
            ->andReturn($orderAfter);

        $deps['vendaPagamentoRepository']
            ->shouldReceive('findOneBy')
            ->once()
            ->with([['id_venda', '=', 501]])
            ->andReturn(null);

        $deps['vendaPagamentoRepository']
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $data): bool {
                return ($data['id_venda'] ?? null) === 501
                    && ($data['id_forma_pgto'] ?? null) === 210
                    && ($data['id_cadastro'] ?? null) === 73;
            }))
            ->andReturn((object) ['id' => 1]);

        $deps['vendaRepository']
            ->shouldReceive('updateSituacaoById')
            ->once()
            ->with(501, 'C');

        $deps['orderRepository']
            ->shouldReceive('updateById')
            ->once()
            ->with(
                99,
                Mockery::on(static function (array $data): bool {
                    return ($data['sync_status'] ?? null) === 'finished_erp'
                        && ($data['id_venda'] ?? null) === 501
                        && ($data['erp_sale_id'] ?? null) === '501'
                        && array_key_exists('error_message', $data)
                        && $data['error_message'] === null;
                }),
            )
            ->andReturn(true);

        $service->markOrderFinished(1, 'ORD-FALLBACK-001');

        $this->assertTrue(true);
    }

    #[Test]
    public function replay_de_order_cancel_sem_pedido_nao_deve_lancar_erro(): void
    {
        [$service, $deps] = $this->makeService();

        $deps['orderRepository']
            ->shouldReceive('findByShopIdAndOrderId')
            ->once()
            ->with(1, 'ORD-CANCEL-001')
            ->andReturn(null);

        $service->markOrderCanceled(1, 'ORD-CANCEL-001');

        $this->assertTrue(true);
    }

    /**
     * @return array{0: Food99OrderErpSyncService, 1: array<string, mixed>}
     */
    private function makeService(): array
    {
        $deps = [
            'shopRepository' => Mockery::mock(Food99ShopRepositoryInterface::class),
            'orderRepository' => Mockery::mock(Food99OrderRepositoryInterface::class),
            'orderItemRepository' => Mockery::mock(Food99OrderItemRepositoryInterface::class),
            'shopItemRepository' => Mockery::mock(Food99ShopItemRepositoryInterface::class),
            'clienteRepository' => Mockery::mock(ClienteRepositoryInterface::class),
            'produtoRepository' => Mockery::mock(ProdutoRepositoryInterface::class),
            'gradeRepository' => Mockery::mock(GradeRepositoryInterface::class),
            'vendaRepository' => Mockery::mock(VendaRepositoryInterface::class),
            'vendaItensRepository' => Mockery::mock(VendaItensRepositoryInterface::class),
            'vendaPagamentoRepository' => Mockery::mock(VendaPagamentoRepositoryInterface::class),
            'vendaInformacoesRepository' => Mockery::mock(VendaInformacoesRepositoryInterface::class),
            'webcUsuarioRepository' => Mockery::mock(WebcUsuarioRepositoryInterface::class),
        ];

        $service = new Food99OrderErpSyncService(
            $deps['shopRepository'],
            $deps['orderRepository'],
            $deps['orderItemRepository'],
            $deps['shopItemRepository'],
            $deps['clienteRepository'],
            $deps['produtoRepository'],
            $deps['gradeRepository'],
            $deps['vendaRepository'],
            $deps['vendaItensRepository'],
            $deps['vendaPagamentoRepository'],
            $deps['vendaInformacoesRepository'],
            $deps['webcUsuarioRepository'],
        );

        return [$service, $deps];
    }
}
