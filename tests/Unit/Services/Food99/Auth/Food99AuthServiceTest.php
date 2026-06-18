<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Food99\Auth;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Services\Auth\UsuarioLogadoService;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;
use App\Services\Food99\Auth\Food99AuthService;

class Food99AuthServiceTest extends TestCase
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

        Schema::shouldReceive('connection->getColumnListing')
            ->with('food99_app_credentials')
            ->andReturn(['name', 'environment', 'app_id', 'app_secret', 'base_url', 'status', 'updated_at', 'created_at']);

        Schema::shouldReceive('connection->getColumnListing')
            ->with('food99_shops')
            ->andReturn([
                'food99_app_credential_id',
                'id_cadastro',
                'app_shop_id',
                'name',
                'binding_status',
                'auth_status',
                'updated_at',
                'created_at',
                'food99_shop_id',
                'last_synced_at',
            ]);
    }

    #[Test]
    public function deve_obter_url_de_autorizacao_da_api_externa(): void
    {
        Http::fake([
            'https://food99.test/v1/auth/authorizationpage/getUrl' => Http::response([
                'errno' => 0,
                'data' => [
                    'url' => 'https://auth.food99.test/authorize?state=abc',
                ],
            ], 200),
        ]);

        $tokenRepository = Mockery::mock(Food99StoreTokenRepositoryInterface::class);
        $usuarioLogado = Mockery::mock(UsuarioLogadoService::class);
        $usuarioLogado->shouldReceive('getIdCadastroLogado')->once()->andReturn(123);

        $shopRepository = Mockery::mock(Food99ShopRepositoryInterface::class);
        $shopRepository->shouldReceive('findByCredentialAndAppShopId')->once()->with(1, 'wc-123')->andReturn(null);
        $shopRepository->shouldReceive('create')->once()->andReturn((object) ['id' => 55]);
        $shopRepository->shouldReceive('find')->once()->with(55)->andReturn((object) [
            'id' => 55,
            'id_cadastro' => 123,
            'app_shop_id' => 'wc-123',
            'binding_status' => 'pending',
            'auth_status' => 'pending',
        ]);

        $appCredentialRepository = Mockery::mock(Food99AppCredentialRepositoryInterface::class);
        $appCredentialRepository->shouldReceive('findLatestByAppId')->once()->with('app-test')->andReturn((object) ['id' => 1]);
        $appCredentialRepository->shouldReceive('update')->once()->with(Mockery::type('array'), 1)->andReturn(true);

        $service = new Food99AuthService(
            $tokenRepository,
            $usuarioLogado,
            $shopRepository,
            $appCredentialRepository,
        );

        $response = $service->getAuthorizationUrl();

        $this->assertSame(
            'https://auth.food99.test/authorize?state=abc',
            $response['authorization_url'],
        );
        $this->assertArrayNotHasKey('response', $response);
    }

    #[Test]
    public function deve_fazer_fallback_para_refresh_quando_get_token_retorna_erro_de_negocio(): void
    {
        Http::fakeSequence()
            ->push([
                'errno' => 1001,
                'msg' => 'authorization expired',
            ], 200)
            ->push([
                'errno' => 0,
                'msg' => 'refresh ok',
            ], 200)
            ->push([
                'errno' => 0,
                'data' => [
                    'auth_token' => 'token-renovado',
                    'token_expiration_time' => '1765000000',
                ],
            ], 200)
            ->push([
                'errno' => 0,
                'data' => [
                    'shop_id' => 'food99-77',
                    'shop_name' => 'Loja Teste',
                ],
            ], 200);

        $repository = Mockery::mock(Food99StoreTokenRepositoryInterface::class);
        $repository->shouldReceive('upsertByAppShopId')
            ->once()
            ->with(
                'shop_123',
                Mockery::on(static function (array $payload): bool {
                    return ($payload['auth_token'] ?? null) === 'token-renovado'
                        && array_key_exists('last_refreshed_at', $payload)
                        && array_key_exists('last_retrieved_at', $payload)
                        && is_array($payload['payload'] ?? null);
                }),
            )
            ->andReturn((object) ['id' => 99]);

        $usuarioLogado = Mockery::mock(UsuarioLogadoService::class);
        $usuarioLogado->shouldReceive('getIdCadastroLogado')->once()->andReturn(123);

        $shopRepository = Mockery::mock(Food99ShopRepositoryInterface::class);
        $shopRepository->shouldReceive('findOwnedByAppShopId')->once()->with(123, 'shop_123')->andReturn((object) [
            'id' => 77,
            'id_cadastro' => 123,
            'app_shop_id' => 'shop_123',
            'binding_status' => 'pending',
            'auth_status' => 'pending',
        ]);
        $shopRepository->shouldReceive('update')->once()->with(Mockery::type('array'), 77)->andReturn(true);
        $shopRepository->shouldReceive('find')->once()->with(77)->andReturn((object) [
            'id' => 77,
            'id_cadastro' => 123,
            'app_shop_id' => 'shop_123',
            'food99_shop_id' => 'food99-77',
            'name' => 'Loja Teste',
            'binding_status' => 'bound',
            'auth_status' => 'active',
        ]);

        $appCredentialRepository = Mockery::mock(Food99AppCredentialRepositoryInterface::class);

        $service = new Food99AuthService(
            $repository,
            $usuarioLogado,
            $shopRepository,
            $appCredentialRepository,
        );
        $response = $service->getTokenByStore('shop_123');

        $this->assertSame('shop_123', $response['app_shop_id']);
        $this->assertTrue($response['token_found']);
        $this->assertArrayNotHasKey('auth_token', $response);
        $this->assertArrayNotHasKey('response', $response);
        $this->assertArrayNotHasKey('refresh_response', $response);
        $this->assertSame(99, $response['persisted_id']);
    }
}
