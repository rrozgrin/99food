<?php

declare(strict_types=1);

namespace App\Services\Food99\Orders;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Food99\SyncFood99OrderToErpJob;
use App\Models\Food99\Orders\Food99Order;
use App\Models\Food99\Orders\Food99OrderItem;
use App\Exceptions\ApiException;
use App\Services\Traits\WithTransaction;
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

class Food99OrderErpSyncService
{
    use WithTransaction;

    public function __construct(
        private readonly Food99ShopRepositoryInterface $shopRepository,
        private readonly Food99OrderRepositoryInterface $orderRepository,
        private readonly Food99OrderItemRepositoryInterface $orderItemRepository,
        private readonly Food99ShopItemRepositoryInterface $shopItemRepository,
        private readonly ClienteRepositoryInterface $clienteRepository,
        private readonly ProdutoRepositoryInterface $produtoRepository,
        private readonly GradeRepositoryInterface $gradeRepository,
        private readonly VendaRepositoryInterface $vendaRepository,
        private readonly VendaItensRepositoryInterface $vendaItensRepository,
        private readonly VendaPagamentoRepositoryInterface $vendaPagamentoRepository,
        private readonly VendaInformacoesRepositoryInterface $vendaInformacoesRepository,
        private readonly WebcUsuarioRepositoryInterface $webcUsuarioRepository,
    ) {}

    public function syncOrderById(int $food99OrderId): void
    {
        $lockKey = sprintf('food99:order-sync:%d', $food99OrderId);
        if (! Cache::add($lockKey, true, now()->addMinutes(2))) {
            return;
        }

        $order = $this->orderRepository->markAsProcessing($food99OrderId);
        try {
            if (! is_object($order)) {
                return;
            }

            $result = $this->transaction(function () use ($order): array {
                $shop = $this->shopRepository->find(id: (int) $order->food99_shop_id);

                if (! is_object($shop)) {
                    throw new ApiException(msg: 'Loja 99Food nao encontrada para sincronizar venda no ERP.', code: 422);
                }

                $idCadastro = (int) data_get($shop, 'id_cadastro');
                if ($idCadastro <= 0) {
                    throw new ApiException(msg: 'id_cadastro da loja 99Food invalido para sincronizar venda no ERP.', code: 422);
                }

                $existingSaleId = $this->findExistingSaleIdByOrderMarker(
                    idCadastro: $idCadastro,
                    orderId: (string) $order->order_id,
                    food99ShopId: (int) $order->food99_shop_id,
                );
                if ($existingSaleId !== null) {
                    return ['id_venda' => $existingSaleId];
                }

                $erpUserId = $this->resolveErpUserId($idCadastro);
                $clienteId = $this->resolveOrCreateClienteId(
                    idCadastro: $idCadastro,
                    order: $order,
                );

                $orderTimestamp = $order->create_time instanceof Carbon
                    ? $order->create_time
                    : Carbon::now();

                $marker = $this->buildOrderMarker(
                    orderId: (string) $order->order_id,
                    food99ShopId: (int) $order->food99_shop_id,
                );

                $venda = $this->vendaRepository->create([
                    'id_tipo_venda' => 7,
                    'id_cadastro' => $idCadastro,
                    'id_usuario' => $erpUserId,
                    'id_cliente' => $clienteId,
                    'data_venda' => $orderTimestamp,
                    'hora_venda' => $orderTimestamp->format('H:i:s'),
                    'situacao' => 'A',
                    'tipo_pgto' => $this->resolveTipoPgto((int) ($order->pay_type ?? 0)),
                    'origem_venda' => 'B2W',
                    'pago' => $order->pay_time instanceof Carbon ? 'S' : 'N',
                    'id_placa' => 0,
                    'descricao_venda' => $marker,
                    'observacao' => mb_substr((string) ($order->remark ?? ''), 0, 255),
                ]);

                $vendaId = (int) data_get($venda, 'id');
                if ($vendaId <= 0) {
                    throw new ApiException(msg: 'Nao foi possivel criar venda no ERP para pedido 99Food.', code: 500);
                }

                $items = $this->orderItemRepository->findByOrderId((int) $order->id) ?? collect();

                foreach ($items as $item) {
                    $productMap = $this->resolveOrCreateProductMap(
                        food99ShopId: (int) $order->food99_shop_id,
                        idCadastro: $idCadastro,
                        erpUserId: $erpUserId,
                        item: $item,
                    );

                    $precoUnitario = $this->toMoneyDecimal((int) ($item->sku_price ?? 0));
                    if ($precoUnitario <= 0) {
                        $precoUnitario = $this->toMoneyDecimal((int) ($item->total_price ?? 0));
                    }

                    $this->vendaItensRepository->create([
                        'qtd' => max(1, (int) ($item->amount ?? 1)),
                        'id_venda' => $vendaId,
                        'id_produto' => $productMap['id_produto'],
                        'id_grade' => $productMap['id_grade'],
                        'nome_produto' => mb_substr((string) ($item->item_name ?? 'Item 99Food'), 0, 255),
                        'preco_tabela' => $precoUnitario,
                        'preco_venda' => $precoUnitario,
                        'codigo_barra' => $productMap['codigo_barra'],
                        'id_cadastro' => $idCadastro,
                    ]);
                }

                $this->vendaInformacoesRepository->create([
                    'id_venda' => $vendaId,
                    'valor_frete' => 0,
                    'volumes' => 1,
                    'id_cadastro' => $idCadastro,
                    'info_adicional' => $marker,
                ]);

                return ['id_venda' => $vendaId];
            });

            $this->orderRepository->updateById(
                food99OrderId: $food99OrderId,
                data: [
                    'id_venda' => (int) $result['id_venda'],
                    'erp_sale_id' => (string) $result['id_venda'],
                    'erp_synced_at' => now(),
                    'sync_status' => 'synced',
                    'error_message' => null,
                ],
            );
        } catch (Throwable $throwable) {
            $this->orderRepository->updateById(
                food99OrderId: $food99OrderId,
                data: [
                    'sync_status' => 'pending_sync',
                    'error_message' => mb_substr($throwable->getMessage(), 0, 1000),
                ],
            );

            throw $throwable;
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function markOrderFinished(int $food99ShopId, string $orderId): void
    {
        $this->markOrderSituation(
            food99ShopId: $food99ShopId,
            orderId: $orderId,
            situacao: 'C',
            syncStatus: 'synced',
            createSaleWhenMissing: true,
        );
    }

    public function markOrderCanceled(int $food99ShopId, string $orderId): void
    {
        $this->markOrderSituation(
            food99ShopId: $food99ShopId,
            orderId: $orderId,
            situacao: 'E',
            syncStatus: 'canceled',
        );
    }

    /**
     * Lista pedidos com problema ou aguardando sync do ERP.
     *
     * @return array<string, mixed>
     */
    public function listSyncQueue(
        int $idCadastro,
        int $limit = 50,
        ?string $appShopId = null,
    ): array {
        $shops = $this->shopRepository->findOwnedByIdCadastro($idCadastro);
        $shopIds = [];
        if (is_object($shops)) {
            $shopIds = $shops
                ->when(is_string($appShopId) && trim($appShopId) !== '', function ($collection) use ($appShopId) {
                    return $collection->where('app_shop_id', trim((string) $appShopId));
                })
                ->pluck('id')
                ->map(static fn($id): int => (int) $id)
                ->all();
        }

        if ($shopIds === []) {
            return [
                'id_cadastro' => $idCadastro,
                'app_shop_id' => $appShopId,
                'orders' => [],
                'total' => 0,
            ];
        }

        $ordersCollection = $this->orderRepository->listByShopIds(
            shopIds: $shopIds,
            limit: $limit,
        );
        $orders = collect($ordersCollection ?? [])
            ->map(static function (Food99Order $order): array {
                return [
                    'id' => (int) $order->id,
                    'food99_shop_id' => (int) $order->food99_shop_id,
                    'app_shop_id' => (string) $order->app_shop_id,
                    'order_id' => (string) $order->order_id,
                    'status' => is_numeric($order->status) ? (int) $order->status : null,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'sync_status' => (string) $order->sync_status,
                    'id_venda' => is_numeric($order->id_venda) ? (int) $order->id_venda : null,
                    'erp_sale_id' => $order->erp_sale_id,
                    'error_message' => $order->error_message,
                    'create_time' => $order->create_time?->toDateTimeString(),
                    'pay_time' => $order->pay_time?->toDateTimeString(),
                    'complete_time' => $order->complete_time?->toDateTimeString(),
                    'cancel_time' => $order->cancel_time?->toDateTimeString(),
                    'items' => $order->items->map(static function (Food99OrderItem $item): array {
                        return [
                            'id' => (int) $item->id,
                            'app_item_id' => $item->app_item_id,
                            'app_external_id' => $item->app_external_id,
                            'item_name' => $item->item_name,
                            'amount' => (int) $item->amount,
                            'sku_price' => is_numeric($item->sku_price) ? (int) $item->sku_price : null,
                            'total_price' => is_numeric($item->total_price) ? (int) $item->total_price : null,
                            'real_price' => is_numeric($item->real_price) ? (int) $item->real_price : null,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'id_cadastro' => $idCadastro,
            'app_shop_id' => $appShopId,
            'total' => count($orders),
            'orders' => $orders,
        ];
    }

    /**
     * Retorna detalhe de um pedido, validando escopo por id_cadastro.
     *
     * @return array<string, mixed>
     */
    public function getOrderDetail(int $food99OrderId, int $idCadastro): array
    {
        $order = $this->orderRepository->find($food99OrderId);
        if (! is_object($order)) {
            throw new ApiException(msg: 'pedido nao encontrado', code: 404);
        }

        $shop = $this->shopRepository->find((int) data_get($order, 'food99_shop_id'));
        if (! is_object($shop) || (int) data_get($shop, 'id_cadastro') !== $idCadastro) {
            throw new ApiException(msg: 'pedido nao encontrado para o cliente logado', code: 404);
        }

        $items = $this->orderItemRepository->findByOrderId((int) $order->id) ?? collect();

        return [
            'id' => (int) $order->id,
            'food99_shop_id' => (int) $order->food99_shop_id,
            'app_shop_id' => (string) $order->app_shop_id,
            'order_id' => (string) $order->order_id,
            'event_type' => (string) $order->event_type,
            'status' => is_numeric($order->status) ? (int) $order->status : null,
            'pay_type' => is_numeric($order->pay_type) ? (int) $order->pay_type : null,
            'delivery_type' => is_numeric($order->delivery_type) ? (int) $order->delivery_type : null,
            'order_price' => is_numeric($order->order_price) ? (int) $order->order_price : null,
            'real_price' => is_numeric($order->real_price) ? (int) $order->real_price : null,
            'real_pay_price' => is_numeric($order->real_pay_price) ? (int) $order->real_pay_price : null,
            'refund_price' => is_numeric($order->refund_price) ? (int) $order->refund_price : null,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'remark' => $order->remark,
            'sync_status' => (string) $order->sync_status,
            'id_venda' => is_numeric($order->id_venda) ? (int) $order->id_venda : null,
            'erp_sale_id' => $order->erp_sale_id,
            'error_message' => $order->error_message,
            'create_time' => $order->create_time?->toDateTimeString(),
            'pay_time' => $order->pay_time?->toDateTimeString(),
            'complete_time' => $order->complete_time?->toDateTimeString(),
            'cancel_time' => $order->cancel_time?->toDateTimeString(),
            'erp_synced_at' => $order->erp_synced_at?->toDateTimeString(),
            'items' => $items->map(static function (Food99OrderItem $item): array {
                return [
                    'id' => (int) $item->id,
                    'app_item_id' => $item->app_item_id,
                    'app_external_id' => $item->app_external_id,
                    'item_name' => $item->item_name,
                    'amount' => (int) $item->amount,
                    'sku_price' => is_numeric($item->sku_price) ? (int) $item->sku_price : null,
                    'total_price' => is_numeric($item->total_price) ? (int) $item->total_price : null,
                    'real_price' => is_numeric($item->real_price) ? (int) $item->real_price : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Enfileira reprocessamento manual de um pedido, validando escopo por id_cadastro.
     *
     * @return array<string, mixed>
     */
    public function queueReprocess(int $food99OrderId, int $idCadastro): array
    {
        $order = $this->orderRepository->find($food99OrderId);
        if (! is_object($order)) {
            throw new ApiException(msg: 'pedido nao encontrado', code: 404);
        }

        $shop = $this->shopRepository->find((int) data_get($order, 'food99_shop_id'));
        if (! is_object($shop) || (int) data_get($shop, 'id_cadastro') !== $idCadastro) {
            throw new ApiException(msg: 'pedido nao encontrado para o cliente logado', code: 404);
        }

        SyncFood99OrderToErpJob::dispatch($food99OrderId);

        return [
            'food99_order_id' => $food99OrderId,
            'order_id' => (string) data_get($order, 'order_id'),
            'sync_status' => (string) data_get($order, 'sync_status'),
            'status' => 'queued',
            'message' => 'sync enfileirado',
        ];
    }

    private function markOrderSituation(
        int $food99ShopId,
        string $orderId,
        string $situacao,
        string $syncStatus,
        bool $createSaleWhenMissing = false,
    ): void {
        $order = $this->orderRepository->findByShopIdAndOrderId($food99ShopId, $orderId);

        if (! is_object($order)) {
            return;
        }

        $shop = $this->shopRepository->find(id: $food99ShopId);
        $idCadastro = is_object($shop) ? (int) data_get($shop, 'id_cadastro', 0) : 0;

        $vendaId = is_numeric($order->id_venda) ? (int) $order->id_venda : null;
        if ($vendaId === null || $vendaId <= 0) {
            $vendaId = $this->findExistingSaleIdByOrderMarker(
                idCadastro: $idCadastro,
                orderId: $orderId,
                food99ShopId: $food99ShopId,
            );
        }

        // Fallback para orderFinish: se ainda nao houver venda, tenta criar via sync completo.
        if (($vendaId === null || $vendaId <= 0) && $createSaleWhenMissing) {
            $this->syncOrderById((int) $order->id);

            $order = $this->orderRepository->find((int) $order->id);
            $vendaId = is_object($order) && is_numeric(data_get($order, 'id_venda'))
                ? (int) data_get($order, 'id_venda')
                : null;

            if ($vendaId === null || $vendaId <= 0) {
                $vendaId = $this->findExistingSaleIdByOrderMarker(
                    idCadastro: $idCadastro,
                    orderId: $orderId,
                    food99ShopId: $food99ShopId,
                );
            }
        }

        if ($situacao === 'C' && $vendaId !== null && $vendaId > 0 && $idCadastro > 0) {
            $this->ensureVendaPagamentoForOrder(
                vendaId: $vendaId,
                order: $order,
                idCadastro: $idCadastro,
            );
        }

        if ($vendaId !== null && $vendaId > 0) {
            $this->vendaRepository->updateSituacaoById(
                idVenda: $vendaId,
                situacao: $situacao,
            );
        }

        $resolvedSyncStatus = $syncStatus;
        if (($vendaId === null || $vendaId <= 0) && $createSaleWhenMissing) {
            $resolvedSyncStatus = 'pending_sync';
        }

        $updates = [
            'sync_status' => $resolvedSyncStatus,
            'error_message' => ($vendaId === null || $vendaId <= 0) && $createSaleWhenMissing
                ? 'Venda ERP nao vinculada no fallback do orderFinish.'
                : null,
        ];

        if ($situacao === 'C') {
            $updates['complete_time'] = now();
        }

        if ($situacao === 'E') {
            $updates['cancel_time'] = now();
        }

        if ($vendaId !== null && $vendaId > 0) {
            $updates['id_venda'] = $vendaId;
            $updates['erp_sale_id'] = (string) $vendaId;
        }

        $this->orderRepository->updateById(
            food99OrderId: (int) $order->id,
            data: $updates,
        );
    }

    private function resolveErpUserId(int $idCadastro): int
    {
        $erpUserId = $this->webcUsuarioRepository->findActiveIdByCadastro($idCadastro);
        if (is_numeric($erpUserId)) {
            return (int) $erpUserId;
        }

        return 1;
    }

    private function resolveOrCreateClienteId(int $idCadastro, Food99Order $order): int
    {
        $nome = trim((string) ($order->customer_name ?? 'Cliente 99Food'));
        if ($nome === '') {
            $nome = 'Cliente 99Food';
        }

        $telefone = $this->sanitizePhone($order->customer_phone);

        $existing = $this->clienteRepository->findByCadastroNomeTelefone(
            idCadastro: $idCadastro,
            nome: $nome,
            telefone: $telefone !== '' ? $telefone : null,
        );

        if (is_object($existing) && is_numeric(data_get($existing, 'id'))) {
            return (int) data_get($existing, 'id');
        }

        $cliente = $this->clienteRepository->create([
            'id_cadastro' => $idCadastro,
            'tipo_pessoa' => 'F',
            'nome' => mb_substr($nome, 0, 120),
            'telefone' => $telefone !== '' ? mb_substr($telefone, 0, 20) : null,
            'celular' => $telefone !== '' ? mb_substr($telefone, 0, 20) : null,
            'ativo' => 'A',
        ]);

        return (int) data_get($cliente, 'id');
    }

    /**
     * @return array{id_produto:int,id_grade:?int,codigo_barra:?string}
     */
    private function resolveOrCreateProductMap(
        int $food99ShopId,
        int $idCadastro,
        int $erpUserId,
        Food99OrderItem $item,
    ): array {
        $appItemId = trim((string) ($item->app_item_id ?? ''));
        if ($appItemId === '') {
            $appItemId = trim((string) ($item->app_external_id ?? ''));
        }
        if ($appItemId === '') {
            $appItemId = 'webhook-item-' . (string) data_get($item, 'id');
        }

        $shopItem = $this->shopItemRepository->findByShopIdAndAppItemId(
            food99ShopId: $food99ShopId,
            appItemId: $appItemId,
        );

        $idProduto = is_object($shopItem) && is_numeric(data_get($shopItem, 'id_produto'))
            ? (int) data_get($shopItem, 'id_produto')
            : 0;

        $idGrade = is_object($shopItem) && is_numeric(data_get($shopItem, 'id_grade'))
            ? (int) data_get($shopItem, 'id_grade')
            : null;

        if ($idProduto <= 0) {
            $created = $this->createProdutoAndGrade(
                idCadastro: $idCadastro,
                erpUserId: $erpUserId,
                itemName: (string) ($item->item_name ?? 'Item 99Food'),
                unitPriceCents: (int) ($item->sku_price ?? 0),
                appItemId: $appItemId,
            );

            $idProduto = $created['id_produto'];
            $idGrade = $created['id_grade'];

            $attributes = [
                'food99_shop_id' => $food99ShopId,
                'app_item_id' => $appItemId,
            ];

            $values = [
                'id_cadastro' => $idCadastro,
                'id_produto' => $idProduto,
                'id_grade' => $idGrade,
                'app_external_id' => $item->app_external_id,
                'item_name' => mb_substr((string) ($item->item_name ?? 'Item 99Food'), 0, 50),
                'price_source' => 'grade',
                'price_cents' => (int) ($item->sku_price ?? 0),
                'price_amount' => $this->toMoneyDecimal((int) ($item->sku_price ?? 0)),
                'is_active' => true,
            ];

            $this->shopItemRepository->updateOrCreate(
                attributes: $attributes,
                values: $values,
            );
        } elseif ($idGrade === null) {
            $grade = $this->gradeRepository->findLatestByProdutoId($idProduto);

            $idGrade = is_object($grade) && is_numeric(data_get($grade, 'id_grade'))
                ? (int) data_get($grade, 'id_grade')
                : null;

            if (is_object($shopItem) && $idGrade !== null) {
                $this->shopItemRepository->update(
                    data: ['id_grade' => $idGrade],
                    id: (int) $shopItem->id,
                );
            }
        }

        $codigoBarra = $this->produtoRepository->findCodigoBarraById($idProduto);

        return [
            'id_produto' => $idProduto,
            'id_grade' => $idGrade,
            'codigo_barra' => is_string($codigoBarra) ? $codigoBarra : null,
        ];
    }

    /**
     * @return array{id_produto:int,id_grade:?int}
     */
    private function createProdutoAndGrade(
        int $idCadastro,
        int $erpUserId,
        string $itemName,
        int $unitPriceCents,
        string $appItemId,
    ): array {
        $name = trim($itemName) !== '' ? trim($itemName) : 'Item 99Food';
        $price = $this->toMoneyDecimal($unitPriceCents);
        $codigoBarra = $this->buildProductBarcode($idCadastro, $appItemId);

        $produto = $this->produtoRepository->create([
            'descricao' => mb_substr($name, 0, 255),
            'id_cadastro' => $idCadastro,
            'id_usuario' => $erpUserId,
            'data_cadastro' => now(),
            'ativo' => 1,
            'codigo_barra' => $codigoBarra,
            'barra' => $codigoBarra,
            'ean' => $codigoBarra,
            'identificacao_interna' => mb_substr('99F-' . ($appItemId !== '' ? $appItemId : uniqid()), 0, 60),
            'custo' => max(0.01, $price),
            'custo_medio_venda' => max(0.01, $price),
            'custo_medio_venda_atacado' => max(0.01, $price),
            'qtd_minima' => 0,
            'locacao_quantidade' => 0,
        ]);

        $idProduto = (int) data_get($produto, 'id');
        if ($idProduto <= 0) {
            throw new ApiException(msg: 'Nao foi possivel criar produto no ERP para item 99Food.', code: 500);
        }

        // A trigger de produto cria/ativa a grade automaticamente.
        $grade = $this->gradeRepository->findLatestByProdutoId($idProduto);

        $idGrade = is_object($grade) && is_numeric(data_get($grade, 'id_grade'))
            ? (int) data_get($grade, 'id_grade')
            : null;

        return [
            'id_produto' => $idProduto,
            'id_grade' => $idGrade,
        ];
    }

    private function findExistingSaleIdByOrderMarker(int $idCadastro, string $orderId, int $food99ShopId): ?int
    {
        $marker = $this->buildOrderMarker($orderId, $food99ShopId);

        return $this->vendaRepository->findIdByCadastroOrigemDescricao(
            idCadastro: $idCadastro,
            origemVenda: 'B2W',
            descricaoVenda: $marker,
        );
    }

    private function buildOrderMarker(string $orderId, int $food99ShopId): string
    {
        return sprintf('FOOD99_ORDER:%s|SHOP:%d', $orderId, $food99ShopId);
    }

    private function resolveTipoPgto(int $payType): int
    {
        return $payType > 0 ? $payType : 98;
    }

    private function ensureVendaPagamentoForOrder(int $vendaId, Food99Order $order, int $idCadastro): void
    {
        $existing = $this->vendaPagamentoRepository->findOneBy([
            ['id_venda', '=', $vendaId],
        ]);

        if (is_object($existing)) {
            return;
        }

        $this->vendaPagamentoRepository->create([
            'id_venda' => $vendaId,
            'id_forma_pgto' => 210,
            'valor_pgto' => $this->toMoneyDecimal((int) ($order->real_pay_price ?? $order->order_price ?? 0)),
            'qtd_parcela' => 1,
            'id_cadastro' => $idCadastro,
        ]);
    }

    private function toMoneyDecimal(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function buildProductBarcode(int $idCadastro, string $appItemId): string
    {
        $seed = trim($appItemId) !== '' ? $appItemId : uniqid((string) $idCadastro, true);
        $hash = preg_replace('/\D+/', '', (string) crc32($idCadastro . '|' . $seed));
        if (! is_string($hash)) {
            $hash = (string) random_int(1000000000, 9999999999);
        }

        $hash = str_pad(substr($hash, 0, 12), 12, '0', STR_PAD_LEFT);

        return $hash;
    }

    private function sanitizePhone(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (! is_string($digits)) {
            return '';
        }

        return trim($digits);
    }
}
