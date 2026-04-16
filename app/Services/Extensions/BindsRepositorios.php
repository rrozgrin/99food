<?php

namespace App\Services\Extensions;

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
 * @see \App\Providers\AppServiceProvider — Onde esta classe é instanciada
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class BindsRepositorios
{
    /**
     * Registra todos os bindings de repositórios no container.
     *
     * @param Application $app Container de dependências do Laravel
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
            \App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Auth\Food99ShopEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Auth\Food99AppCredentialRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Auth\Food99AppCredentialEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Auth\Food99StoreTokenRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Auth\Food99StoreTokenEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Catalog\Food99ShopMenuRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopMenuEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopCategoryEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopItemEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Catalog\Food99ShopCategoryItemRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Catalog\Food99ShopCategoryItemEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Catalog\Food99PublishJobRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Catalog\Food99PublishJobEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Webhook\Food99WebhookInboundLogRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Webhook\Food99WebhookInboundLogEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Orders\Food99OrderRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Orders\Food99OrderEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\Food99\Orders\Food99OrderItemRepositoryInterface::class,
            \App\Repository\Eloquent\Models\Food99\Orders\Food99OrderItemEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\ClienteRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\ClienteEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\ProdutoRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\ProdutoEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\GradeRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\GradeEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\VendaRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\VendaEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\VendaItensRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\VendaItensEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\VendaPagamentoRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\VendaPagamentoEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\VendaInformacoesRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\VendaInformacoesEloquentRepository::class,
        );

        $app->bind(
            \App\Repository\Contracts\Models\BaseErp\WebcUsuarioRepositoryInterface::class,
            \App\Repository\Eloquent\Models\BaseErp\WebcUsuarioEloquentRepository::class,
        );
    }
}
