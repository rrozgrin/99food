<?php

namespace App\Repository\Contracts;

use Illuminate\Http\Request;

/**
 * Contrato opt-in para repositórios que suportam DataTables server-side.
 *
 * NÃO faz parte do RepositoryInterface base — é uma interface separada
 * que deve ser implementada SOMENTE por repositórios que precisam
 * fornecer dados para jQuery DataTables com server-side processing.
 *
 * Deve ser usada em conjunto com a trait HasDataTables que fornece
 * a implementação completa.
 *
 * Exemplo de uso:
 *
 *     class ProdutosEloquentRepository extends EloquentRepository
 *         implements ProdutosRepositoryInterface, DataTablesInterface
 *     {
 *         use HasDataTables;
 *
 *         protected array $searchableColumns = ['nome', 'sku', 'descricao'];
 *         protected array $orderableColumns  = ['id', 'nome', 'preco', 'data_criacao'];
 *     }
 *
 * @see \App\Repository\Traits\HasDataTables — Trait com a implementação
 * @see RepositoryInterface — Contrato base CRUD (separado)
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
interface DataTablesInterface
{
    /**
     * Retorna dados paginados no formato do jQuery DataTables server-side.
     *
     * Parâmetros esperados na Request (enviados automaticamente pelo DataTables):
     *  - draw: int — Contador de requisição (anti-replay)
     *  - start: int — Offset para paginação
     *  - length: int — Quantidade de registros por página
     *  - search.value: string — Texto de busca global
     *  - order.0.column: int — Índice da coluna para ordenação
     *  - order.0.dir: string — Direção (asc/desc)
     *
     * @param Request $request Requisição HTTP com parâmetros do DataTables
     *
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: array}
     */
    public function dataTables(Request $request): array;
}
