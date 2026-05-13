<?php

namespace App\Repository\Eloquent;

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Model;
use App\Services\Auth\UsuarioLogadoService;
use App\Repository\Contracts\RepositoryInterface;

/**
 * Implementação base abstrata do padrão Repository usando Eloquent.
 *
 * Fornece implementações genéricas para todas as operações do
 * RepositoryInterface utilizando o Eloquent ORM do Laravel.
 *
 * Cada repositório concreto define qual Model será utilizado
 * através da propriedade $model, setada no construtor.
 *
 * O UsuarioLogadoService é injetado automaticamente para que
 * qualquer repositório possa acessar dados do usuário autenticado
 * (útil para auditoria, filtros por usuário, etc.).
 *
 * @see RepositoryInterface — Contrato implementado
 * @see EloquentRepository — Camada de conexão MySQL/testing
 * @see AbstractBaseRepository — Extensão com suporte a DataTables
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
abstract class AbstractEloquentRepository implements RepositoryInterface
{
    /**
     * Model Eloquent utilizado pelo repositório.
     */
    protected Model $model;

    /**
     * Serviço do usuário autenticado via JWT.
     */
    protected readonly UsuarioLogadoService $usuarioLogadoService;

    /**
     * Inicializa o repositório com o serviço de usuário logado.
     * O model é setado pelas classes filhas antes de chamar parent::__construct().
     */
    public function __construct()
    {
        $this->usuarioLogadoService = app(UsuarioLogadoService::class);
    }

    /**
     * {@inheritDoc}
     */
    public function find(mixed $id): ?object
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): ?object
    {
        return $this->model->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(
        array $criteria,
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): ?object {
        $query = $this->model->newQuery();

        foreach ($criteria as $criterion) {
            $query->where($criterion[0], $criterion[1], $criterion[2]);
        }

        foreach ($orderBy as $order) {
            $query->orderBy($order[0], $order[1]);
        }

        if ($limit !== null) {
            $query->take($limit);
        }

        if ($offset !== null) {
            $query->skip($offset);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findOneBy(array $criteria): ?object
    {
        return $this->findBy($criteria)?->first();
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(int $pages): mixed
    {
        return $this->model->paginate($pages);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): object
    {
        return $this->model->create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $data, mixed $id): bool
    {
        return $this->findOrFail($id)->update($data);
    }

    /**
     * Cria ou atualiza uma entidade baseado nos atributos de busca.
     *
     * @param array<string, mixed> $attributes Atributos para busca
     * @param array<string, mixed> $values     Valores a serem atualizados/criados
     *
     * @return object Entidade criada ou atualizada
     */
    public function updateOrCreate(array $attributes, array $values = []): object
    {
        return $this->model->updateOrCreate($attributes, $values);
    }

    /**
     * Busca ou cria uma entidade baseado nos atributos informados.
     *
     * @param array<string, mixed> $attributes Atributos para busca
     * @param array<string, mixed> $values     Valores adicionais para criação
     *
     * @return object Entidade encontrada ou criada
     */
    public function firstOrCreate(array $attributes, array $values = []): object
    {
        return $this->model->firstOrCreate($attributes, $values);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(mixed $id): bool
    {
        return $this->findOrFail($id)->delete();
    }

    /**
     * Busca entidade por ID ou lança exceção de negócio quando não encontrada.
     *
     * @param mixed $id Identificador da entidade
     *
     * @return Model Entidade encontrada
     */
    protected function findOrFail(mixed $id): Model
    {
        $entity = $this->model->find($id);

        if (! $entity instanceof Model) {
            throw new ApiException(
                msg: sprintf('Registro nao encontrado para o ID informado: %s', (string) $id),
                code: 404,
            );
        }

        return $entity;
    }
}
