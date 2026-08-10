<?php

namespace App\Services\Extensions;

use App\Providers\AppServiceProvider;
use App\Repository\Contracts\Models\BaseErp\ClienteRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\GradeRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\ProdutoRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaInformacoesRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaItensRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaPagamentoRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\VendaRepositoryInterface;
use App\Repository\Contracts\Models\BaseErp\WebcUsuarioRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99PublishJobRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryItemRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopMenuRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderItemRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Orders\Food99OrderRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Webhook\Food99WebhookInboundLogRepositoryInterface;
use App\Repository\Eloquent\Models\BaseErp\ClienteEloquentRepository;
use App\Repository\Eloquent\Models\BaseErp\GradeEloquentRepository;
use App\Repository\Eloquent\Models\BaseErp\ProdutoEloquentRepository;
use App\Repository\Eloquent\Models\BaseErp\VendaEloquentRepository;
use App\Repository\Eloquent\Models\BaseErp\VendaInformacoesEloquentRepository;
use App\Repository\Eloquent\Models\BaseErp\VendaItensEloquentRepository;
use App\Repository\Eloquent\Models\BaseErp\VendaPagamentoEloquentRepository;
use App\Repository\Eloquent\Models\BaseErp\WebcUsuarioEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Auth\Food99AppCredentialEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Auth\Food99ShopEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Auth\Food99StoreTokenEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Catalog\Food99PublishJobEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopCategoryEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopCategoryItemEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopItemEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopMenuEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Orders\Food99OrderEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Orders\Food99OrderItemEloquentRepository;
use App\Repository\Eloquent\Models\Food99\Webhook\Food99WebhookInboundLogEloquentRepository;
use Illuminate\Contracts\Foundation\Application;

/**
 * Registro centralizado de bindings Repository Interface → Implementação Eloquent.
 *
 * Esta classe é o ponto único onde todas as interfaces de Repository
 * são vinculadas às suas implementações Eloquent concretas no container
 * de injeção de dependência do Laravel.
 *
 * Chamada no AppServiceProvider::register(), garantindo que todos os
 * bindings estejam disponíveis antes de qualquer resolução de dependência.
 *
 * Para adicionar um novo domínio:
 *  1. Crie a interface em app/Repository/Contracts/Models/{Domínio}/
 *  2. Crie a implementação em app/Repository/Eloquent/Models/{Domínio}/
 *  3. Adicione o binding aqui nesta classe
 *
 * Exemplo:
 *     // No construtor, adicione:
 *     $app->bind(
 *         ProdutosRepositoryInterface::class,
 *         ProdutosEloquentRepository::class,
 *     );
 *
 * @see AppServiceProvider — Onde esta classe é instanciada
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class BindsRepositorios
{
    /**
     * Registra todos os bindings de repositórios no container.
     *
     * @param  Application  $app  Container de dependências do Laravel
     */
    public function __construct(
        protected readonly Application $app,
    ) {
        /*
        |----------------------------------------------------------------------
        | Bindings de Repositórios
        |----------------------------------------------------------------------
        |
        | Adicione novos bindings abaixo seguindo o padrão:
        |
        | $app->bind(
        |     Interface::class,
        |     Implementação::class,
        | );
        |
        | Exemplo (descomente e adapte ao criar um novo domínio):
        |
        | $app->bind(
        |     \App\Repository\Contracts\Models\Categorias\CategoriasRepositoryInterface::class,
        |     \App\Repository\Eloquent\Models\Categorias\CategoriasEloquentRepository::class,
        | );
        |
        */
        $app->bind(
            Food99ShopRepositoryInterface::class,
            Food99ShopEloquentRepository::class,
        );

        $app->bind(
            Food99AppCredentialRepositoryInterface::class,
            Food99AppCredentialEloquentRepository::class,
        );

        $app->bind(
            Food99StoreTokenRepositoryInterface::class,
            Food99StoreTokenEloquentRepository::class,
        );

        $app->bind(
            Food99ShopMenuRepositoryInterface::class,
            Food99ShopMenuEloquentRepository::class,
        );

        $app->bind(
            Food99ShopCategoryRepositoryInterface::class,
            Food99ShopCategoryEloquentRepository::class,
        );

        $app->bind(
            Food99ShopItemRepositoryInterface::class,
            Food99ShopItemEloquentRepository::class,
        );

        $app->bind(
            Food99ShopCategoryItemRepositoryInterface::class,
            Food99ShopCategoryItemEloquentRepository::class,
        );

        $app->bind(
            Food99PublishJobRepositoryInterface::class,
            Food99PublishJobEloquentRepository::class,
        );

        $app->bind(
            Food99WebhookInboundLogRepositoryInterface::class,
            Food99WebhookInboundLogEloquentRepository::class,
        );

        $app->bind(
            Food99OrderRepositoryInterface::class,
            Food99OrderEloquentRepository::class,
        );

        $app->bind(
            Food99OrderItemRepositoryInterface::class,
            Food99OrderItemEloquentRepository::class,
        );

        $app->bind(
            ClienteRepositoryInterface::class,
            ClienteEloquentRepository::class,
        );

        $app->bind(
            ProdutoRepositoryInterface::class,
            ProdutoEloquentRepository::class,
        );

        $app->bind(
            GradeRepositoryInterface::class,
            GradeEloquentRepository::class,
        );

        $app->bind(
            VendaRepositoryInterface::class,
            VendaEloquentRepository::class,
        );

        $app->bind(
            VendaItensRepositoryInterface::class,
            VendaItensEloquentRepository::class,
        );

        $app->bind(
            VendaPagamentoRepositoryInterface::class,
            VendaPagamentoEloquentRepository::class,
        );

        $app->bind(
            VendaInformacoesRepositoryInterface::class,
            VendaInformacoesEloquentRepository::class,
        );

        $app->bind(
            WebcUsuarioRepositoryInterface::class,
            WebcUsuarioEloquentRepository::class,
        );
    }
}
