<?php

namespace App\Repository\Contracts;

/**
 * Interface base do padrão Repository — contrato CRUD puro.
 *
 * Define SOMENTE as operações de persistência genéricas.
 * NÃO inclui DataTables ou qualquer lógica de apresentação.
 *
 * Para repositórios que precisam de DataTables server-side,
 * implemente também DataTablesInterface e use a trait HasDataTables.
 *
 * Hierarquia de classes:
 *
 *     RepositoryInterface (contrato CRUD)
 *         ↓
 *     AbstractEloquentRepository (implementação base com Eloquent)
 *         ↓
 *     EloquentRepository (define conexão MySQL / testing)
 *         ↓
 *     {DomínioEloquentRepository} (implementação específica do domínio)
 *         + opcionalmente: use HasDataTables implements DataTablesInterface
 *
 * Exemplo — repositório simples (sem DataTables):
 *
 *     interface ConfigRepositoryInterface extends RepositoryInterface {}
 *
 *     class ConfigEloquentRepository extends EloquentRepository
 *         implements ConfigRepositoryInterface
 *     {
 *         public function __construct(Config $model) {
 *             parent::__construct($model);
 *         }
 *     }
 *
 * Exemplo — repositório com DataTables:
 *
 *     class ProdutosEloquentRepository extends EloquentRepository
 *         implements ProdutosRepositoryInterface, DataTablesInterface
 *     {
 *         use HasDataTables;
 *
 *         protected array $searchableColumns = ['nome', 'sku'];
 *         protected array $orderableColumns  = ['id', 'nome', 'preco'];
 *     }
 *
 * @see AbstractEloquentRepository — Implementação base
 * @see DataTablesInterface — Contrato opt-in para DataTables
 * @see \App\Repository\Traits\HasDataTables — Trait com implementação DataTables
 * @see \App\Services\Extensions\BindsRepositorios — Registro de bindings
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
interface RepositoryInterface
{
    /**
     * Encontra uma entidade pelo seu ID.
     *
     * @param mixed $id Identificador da entidade
     *
     * @return object|null A entidade encontrada ou null
     */
    public function find(mixed $id): ?object;

    /**
     * Retorna todas as entidades do repositório.
     *
     * @return object|null Coleção de entidades
     */
    public function findAll(): ?object;

    /**
     * Busca entidades por critérios dinâmicos.
     *
     * Cada critério é um array com 3 elementos: [coluna, operador, valor]
     *
     * Exemplo:
     *     $repo->findBy(
     *         criteria: [['status', '=', 'ativo'], ['tipo', '=', 'produto']],
     *         orderBy: [['nome', 'asc']],
     *         limit: 10,
     *     );
     *
     * @param array    $criteria Critérios no formato [[coluna, operador, valor], ...]
     * @param array    $orderBy  Ordenação no formato [[coluna, direção], ...]
     * @param int|null $limit    Limite de resultados
     * @param int|null $offset   Posição inicial
     *
     * @return object|null Coleção de entidades encontradas
     */
    public function findBy(
        array $criteria,
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): ?object;

    /**
     * Encontra uma única entidade pelo critério informado.
     *
     * @param array $criteria Critérios no formato [[coluna, operador, valor], ...]
     *
     * @return object|null A entidade encontrada ou null
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * Pagina os resultados do repositório.
     *
     * @param int $perPage Itens por página
     *
     * @return mixed Instância de LengthAwarePaginator ou similar
     */
    public function paginate(int $perPage): mixed;

    /**
     * Cria uma nova entidade no repositório.
     *
     * @param array<string, mixed> $data Dados da entidade
     *
     * @return object Entidade criada
     */
    public function create(array $data): object;

    /**
     * Atualiza uma entidade existente pelo seu ID.
     *
     * @param array<string, mixed> $data Dados a atualizar
     * @param mixed                $id   Identificador da entidade
     *
     * @return bool True se atualizado com sucesso
     */
    public function update(array $data, mixed $id): bool;

    /**
     * Cria ou atualiza uma entidade com base nos atributos de busca.
     *
     * @param array<string, mixed> $attributes Atributos para busca
     * @param array<string, mixed> $values     Valores para atualizar/criar
     *
     * @return object Entidade criada ou atualizada
     */
    public function updateOrCreate(array $attributes, array $values = []): object;

    /**
     * Busca ou cria uma entidade com base nos atributos informados.
     *
     * @param array<string, mixed> $attributes Atributos para busca
     * @param array<string, mixed> $values     Valores adicionais para criacao
     *
     * @return object Entidade encontrada ou criada
     */
    public function firstOrCreate(array $attributes, array $values = []): object;

    /**
     * Remove uma entidade pelo seu ID.
     *
     * @param mixed $id Identificador da entidade
     *
     * @return bool True se removido com sucesso
     */
    public function delete(mixed $id): bool;
}
