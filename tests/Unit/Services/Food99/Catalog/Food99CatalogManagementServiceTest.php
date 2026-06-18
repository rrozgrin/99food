<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Food99\Catalog;

use Mockery;
use Closure;
use stdClass;
use Tests\TestCase;
use App\Exceptions\ApiException;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Food99\Catalog\Food99CatalogManagementService;
use App\Repository\Contracts\Models\BaseErp\GradeRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\ProdutoRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopMenuRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryItemRepositoryInterface;

class Food99CatalogManagementServiceTest extends TestCase
{
    private UsuarioLogadoService $usuarioLogado;
    private Food99ShopRepositoryInterface $shopRepository;
    private Food99StoreTokenRepositoryInterface $storeTokenRepository;
    private Food99ShopMenuRepositoryInterface $shopMenuRepository;
    private Food99ShopCategoryRepositoryInterface $shopCategoryRepository;
    private Food99ShopItemRepositoryInterface $shopItemRepository;
    private Food99ShopCategoryItemRepositoryInterface $shopCategoryItemRepository;
    private ProdutoRepositoryInterface $produtoRepository;
    private GradeRepositoryInterface $gradeRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuarioLogado = Mockery::mock(UsuarioLogadoService::class);
        $this->shopRepository = Mockery::mock(Food99ShopRepositoryInterface::class);
        $this->storeTokenRepository = Mockery::mock(Food99StoreTokenRepositoryInterface::class);
        $this->shopMenuRepository = Mockery::mock(Food99ShopMenuRepositoryInterface::class);
        $this->shopCategoryRepository = Mockery::mock(Food99ShopCategoryRepositoryInterface::class);
        $this->shopItemRepository = Mockery::mock(Food99ShopItemRepositoryInterface::class);
        $this->shopCategoryItemRepository = Mockery::mock(Food99ShopCategoryItemRepositoryInterface::class);
        $this->produtoRepository = Mockery::mock(ProdutoRepositoryInterface::class);
        $this->gradeRepository = Mockery::mock(GradeRepositoryInterface::class);

        $this->usuarioLogado->shouldReceive('getIdCadastroLogado')
            ->byDefault()
            ->andReturn(321);
    }

    #[Test]
    public function configura_item_draft_existente_e_cria_vinculo_categoria_item(): void
    {
        $service = $this->makeService();
        $shop = $this->makeObject(['id' => 10]);
        $category = $this->makeObject(['id' => 22, 'food99_shop_menu_id' => 7]);
        $item = $this->makeObject([
            'id' => 99,
            'food99_shop_id' => 10,
            'food99_shop_category_id' => 11,
            'id_produto' => 123,
            'id_grade' => 456,
            'app_item_id' => 'p123_g456',
            'app_external_id' => 'erp-abc',
            'publish_status' => 'draft',
        ]);
        $savedItem = $this->makeObject([
            'id' => 99,
            'food99_shop_id' => 10,
            'food99_shop_category_id' => 22,
            'id_produto' => 123,
            'id_grade' => 456,
            'app_item_id' => 'p123_g456',
            'app_external_id' => 'erp-abc',
            'item_name' => 'Hamburguer Classico',
            'short_desc' => 'Descricao opcional',
            'price_amount' => 29.9,
            'price_cents' => 2990,
            'is_active' => true,
            'payload_snapshot' => ['auth_token' => 'token-nao-deve-vazar'],
        ]);

        $this->shopRepository->shouldReceive('findOwnedByAppShopId')
            ->once()
            ->with(321, 'wc-sandbox-002')
            ->andReturn($shop);
        $this->shopCategoryRepository->shouldReceive('findByShopIdAndAppCategoryId')
            ->once()
            ->with(10, 'hamburgueres')
            ->andReturn($category);
        $this->shopItemRepository->shouldReceive('findByShopIdAndAppItemId')
            ->once()
            ->with(10, 'p123_g456')
            ->andReturn($item);
        $this->shopItemRepository->shouldReceive('update')
            ->once()
            ->with([
                'food99_shop_category_id' => 22,
                'item_name' => 'Hamburguer Classico',
                'short_desc' => 'Descricao opcional',
                'is_active' => true,
                'price_amount' => 29.9,
                'price_cents' => 2990,
            ], 99)
            ->andReturn(true);
        $this->shopCategoryItemRepository->shouldReceive('deleteByItemId')
            ->once()
            ->with(99)
            ->andReturn(1);
        $this->shopCategoryItemRepository->shouldReceive('updateOrCreate')
            ->once()
            ->with([
                'food99_shop_category_id' => 22,
                'food99_shop_item_id' => 99,
            ], [
                'sort_order' => 0,
            ])
            ->andReturn($this->makeObject([]));
        $this->shopItemRepository->shouldReceive('findByShopIdAndAppItemId')
            ->once()
            ->with(10, 'p123_g456')
            ->andReturn($savedItem);

        $result = $service->configureItem([
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_id' => 'p123_g456',
            'app_category_id' => 'hamburgueres',
            'item_name' => 'Hamburguer Classico',
            'short_desc' => 'Descricao opcional',
            'price_amount' => 29.90,
            'is_active' => true,
        ]);

        $this->assertSame(123, $result['id_produto']);
        $this->assertSame(456, $result['id_grade']);
        $this->assertSame('erp-abc', $result['app_external_id']);
        $this->assertSame(22, $result['food99_shop_category_id']);
        $this->assertArrayNotHasKey('payload_snapshot', $result);
    }

    #[Test]
    public function nao_permte_configurar_item_inexistente(): void
    {
        $service = $this->makeService();
        $shop = $this->makeObject(['id' => 10]);
        $category = $this->makeObject(['id' => 22]);

        $this->shopRepository->shouldReceive('findOwnedByAppShopId')
            ->once()
            ->with(321, 'wc-sandbox-002')
            ->andReturn($shop);
        $this->shopCategoryRepository->shouldReceive('findByShopIdAndAppCategoryId')
            ->once()
            ->with(10, 'hamburgueres')
            ->andReturn($category);
        $this->shopItemRepository->shouldReceive('findByShopIdAndAppItemId')
            ->once()
            ->with(10, 'p123_g456')
            ->andReturn(null);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('O ERP ainda nao criou o draft');

        $service->configureItem([
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_id' => 'p123_g456',
            'app_category_id' => 'hamburgueres',
        ]);
    }

    #[Test]
    public function valida_categoria_inexistente(): void
    {
        $service = $this->makeService();
        $shop = $this->makeObject(['id' => 10]);

        $this->shopRepository->shouldReceive('findOwnedByAppShopId')
            ->once()
            ->with(321, 'wc-sandbox-002')
            ->andReturn($shop);
        $this->shopCategoryRepository->shouldReceive('findByShopIdAndAppCategoryId')
            ->once()
            ->with(10, 'hamburgueres')
            ->andReturn(null);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $service->configureItem([
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_id' => 'p123_g456',
            'app_category_id' => 'hamburgueres',
        ]);
    }

    #[Test]
    public function preserva_id_produto_e_id_grade_do_item_existente(): void
    {
        $service = $this->makeService();
        $shop = $this->makeObject(['id' => 10]);
        $category = $this->makeObject(['id' => 22]);
        $item = $this->makeObject([
            'id' => 99,
            'id_produto' => 777,
            'id_grade' => 888,
            'app_item_id' => 'p123_g456',
        ]);
        $savedItem = $this->makeObject([
            'id' => 99,
            'id_produto' => 777,
            'id_grade' => 888,
            'app_item_id' => 'p123_g456',
            'food99_shop_category_id' => 22,
        ]);

        $this->shopRepository->shouldReceive('findOwnedByAppShopId')->once()->with(321, 'wc-sandbox-002')->andReturn($shop);
        $this->shopCategoryRepository->shouldReceive('findByShopIdAndAppCategoryId')->once()->with(10, 'hamburgueres')->andReturn($category);
        $this->shopItemRepository->shouldReceive('findByShopIdAndAppItemId')->once()->with(10, 'p123_g456')->andReturn($item);
        $this->shopItemRepository->shouldReceive('update')
            ->once()
            ->with(['food99_shop_category_id' => 22], 99)
            ->andReturn(true);
        $this->shopCategoryItemRepository->shouldReceive('deleteByItemId')->once()->with(99)->andReturn(1);
        $this->shopCategoryItemRepository->shouldReceive('updateOrCreate')->once()->andReturn($this->makeObject([]));
        $this->shopItemRepository->shouldReceive('findByShopIdAndAppItemId')->once()->with(10, 'p123_g456')->andReturn($savedItem);

        $result = $service->configureItem([
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_id' => 'p123_g456',
            'app_category_id' => 'hamburgueres',
        ]);

        $this->assertSame(777, $result['id_produto']);
        $this->assertSame(888, $result['id_grade']);
    }

    #[Test]
    public function aceita_configurar_item_published_para_atualizacao(): void
    {
        $result = $this->assertConfigurableStatus('published');

        $this->assertSame('published', $result['publish_status']);
    }

    #[Test]
    public function aceita_configurar_item_queued_para_atualizacao(): void
    {
        $result = $this->assertConfigurableStatus('queued');

        $this->assertSame('queued', $result['publish_status']);
    }

    #[Test]
    public function lista_itens_com_identificadores_de_dominio_para_o_front(): void
    {
        $service = $this->makeService();

        $this->shopRepository->shouldReceive('findOwnedByAppShopId')->once()->with(321, 'wc-sandbox-002')->andReturn($this->makeObject(['id' => 10]));
        $this->shopMenuRepository->shouldReceive('findByShopId')->once()->with(10)->andReturn(collect([
            ['id' => 7, 'app_menu_id' => 'principal', 'menu_name' => 'Principal'],
        ]));
        $this->shopCategoryRepository->shouldReceive('findByShopId')->once()->with(10)->andReturn(collect([
            ['id' => 22, 'food99_shop_menu_id' => 7, 'app_category_id' => 'hamburgueres', 'category_name' => 'Hamburgueres'],
        ]));
        $this->shopItemRepository->shouldReceive('findByShopId')->once()->with(10)->andReturn(collect([
            [
                'id' => 99,
                'food99_shop_category_id' => 22,
                'app_item_id' => 'p123_g456',
                'item_name' => 'Hamburguer',
                'payload_snapshot' => ['auth_token' => 'nao-vaza'],
            ],
        ]));

        $result = $service->listItems('wc-sandbox-002');

        $this->assertSame('hamburgueres', $result['items'][0]['app_category_id']);
        $this->assertSame('Hamburgueres', $result['items'][0]['category_name']);
        $this->assertSame('principal', $result['items'][0]['app_menu_id']);
        $this->assertSame('Principal', $result['items'][0]['menu_name']);
        $this->assertArrayNotHasKey('payload_snapshot', $result['items'][0]);
    }

    #[Test]
    public function lista_categorias_com_app_menu_id_para_o_front(): void
    {
        $service = $this->makeService();

        $this->shopRepository->shouldReceive('findOwnedByAppShopId')->once()->with(321, 'wc-sandbox-002')->andReturn($this->makeObject(['id' => 10]));
        $this->shopMenuRepository->shouldReceive('findByShopId')->once()->with(10)->andReturn(collect([
            ['id' => 7, 'app_menu_id' => 'principal', 'menu_name' => 'Principal'],
        ]));
        $this->shopCategoryRepository->shouldReceive('findByShopId')->once()->with(10)->andReturn(collect([
            ['id' => 22, 'food99_shop_menu_id' => 7, 'app_category_id' => 'hamburgueres', 'category_name' => 'Hamburgueres'],
        ]));

        $result = $service->listCategories('wc-sandbox-002');

        $this->assertSame('principal', $result['categories'][0]['app_menu_id']);
        $this->assertSame('Principal', $result['categories'][0]['menu_name']);
    }

    private function makeService(): Food99CatalogManagementService
    {
        return new class(
            $this->usuarioLogado,
            $this->shopRepository,
            $this->storeTokenRepository,
            $this->shopMenuRepository,
            $this->shopCategoryRepository,
            $this->shopItemRepository,
            $this->shopCategoryItemRepository,
            $this->produtoRepository,
            $this->gradeRepository,
        ) extends Food99CatalogManagementService {
            protected function transaction(Closure $callback, int $attempts = 1): mixed
            {
                return $callback();
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function assertConfigurableStatus(string $status): array
    {
        $service = $this->makeService();
        $shop = $this->makeObject(['id' => 10]);
        $category = $this->makeObject(['id' => 22]);
        $item = $this->makeObject([
            'id' => 99,
            'publish_status' => $status,
            'app_item_id' => 'p123_g456',
        ]);
        $savedItem = $this->makeObject([
            'id' => 99,
            'publish_status' => $status,
            'app_item_id' => 'p123_g456',
            'food99_shop_category_id' => 22,
        ]);

        $this->shopRepository->shouldReceive('findOwnedByAppShopId')->once()->with(321, 'wc-sandbox-002')->andReturn($shop);
        $this->shopCategoryRepository->shouldReceive('findByShopIdAndAppCategoryId')->once()->with(10, 'hamburgueres')->andReturn($category);
        $this->shopItemRepository->shouldReceive('findByShopIdAndAppItemId')->once()->with(10, 'p123_g456')->andReturn($item);
        $this->shopItemRepository->shouldReceive('update')->once()->with(['food99_shop_category_id' => 22], 99)->andReturn(true);
        $this->shopCategoryItemRepository->shouldReceive('deleteByItemId')->once()->with(99)->andReturn(1);
        $this->shopCategoryItemRepository->shouldReceive('updateOrCreate')->once()->andReturn($this->makeObject([]));
        $this->shopItemRepository->shouldReceive('findByShopIdAndAppItemId')->once()->with(10, 'p123_g456')->andReturn($savedItem);

        return $service->configureItem([
            'app_shop_id' => 'wc-sandbox-002',
            'app_item_id' => 'p123_g456',
            'app_category_id' => 'hamburgueres',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function makeObject(array $data): object
    {
        return new class($data) extends stdClass
        {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(array $data)
            {
                foreach ($data as $key => $value) {
                    $this->{$key} = $value;
                }
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return get_object_vars($this);
            }
        };
    }
}
