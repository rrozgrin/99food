<?php

namespace App\Repository\Traits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait opt-in para suporte a jQuery DataTables server-side processing.
 *
 * Fornece uma implementação robusta e moderna para DataTables,
 * com busca multi-coluna, ordenação configurável e transformação
 * opcional dos dados de saída.
 *
 * USO: Adicione esta trait + DataTablesInterface ao repositório.
 * A classe que usa esta trait DEVE ter a propriedade $model (Model Eloquent).
 *
 * Propriedades configuráveis (defina na classe concreta):
 *
 *     // Colunas onde o DataTables faz busca global (OR entre elas)
 *     protected array $searchableColumns = ['nome', 'email', 'cpf'];
 *
 *     // Colunas ordenáveis — índice corresponde ao índice da coluna no DT
 *     protected array $orderableColumns = ['id', 'nome', 'email', 'data_criacao'];
 *
 *     // Coluna e direção padrão de ordenação
 *     protected string $defaultOrderColumn    = 'id';
 *     protected string $defaultOrderDirection = 'desc';
 *
 * Se nenhuma propriedade for definida, usa defaults seguros.
 *
 * Para personalizar a transformação dos dados, sobrescreva
 * o método transformForDataTables(). O default retorna toArray().
 *
 * Exemplo completo:
 *
 *     class UsuariosEloquentRepository extends EloquentRepository
 *         implements UsuariosRepositoryInterface, DataTablesInterface
 *     {
 *         use HasDataTables;
 *
 *         protected array $searchableColumns    = ['nome', 'login', 'email'];
 *         protected array $orderableColumns     = ['id', 'nome', 'login', 'data_criacao'];
 *         protected string $defaultOrderColumn  = 'data_criacao';
 *
 *         // Opcional — personalizar a saída
 *         protected function transformForDataTables(mixed $item): array
 *         {
 *             return [
 *                 'id'    => $item->id,
 *                 'nome'  => $item->nome,
 *                 'login' => $item->login,
 *                 'ativo' => $item->ativo === 'S' ? 'Ativo' : 'Inativo',
 *             ];
 *         }
 *     }
 *
 * @see \App\Repository\Contracts\DataTablesInterface — Contrato
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
trait HasDataTables
{
    /**
     * Retorna dados paginados no formato do jQuery DataTables server-side.
     *
     * Fluxo:
     *  1. Lê parâmetros do DataTables da Request
     *  2. Conta total de registros (sem filtro)
     *  3. Aplica busca global (OR em todas as searchableColumns)
     *  4. Conta registros filtrados
     *  5. Aplica ordenação e paginação
     *  6. Transforma cada registro via transformForDataTables()
     *  7. Retorna no formato { draw, recordsTotal, recordsFiltered, data }
     *
     * @param Request $request Requisição com parâmetros do DataTables
     *
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: array}
     */
    public function dataTables(Request $request): array
    {
        $draw       = (int) $request->input('draw', 1);
        $start      = (int) $request->input('start', 0);
        $length     = (int) $request->input('length', 10);
        $search     = $request->input('search.value', '');
        $orderIndex = $request->input('order.0.column');
        $orderDir   = $request->input('order.0.dir', $this->getDefaultOrderDirection());

        // Total geral de registros (sem filtro)
        $totalRecords = $this->model->newQuery()->count();

        // Query base com busca aplicada
        $query = $this->applyDataTablesSearch(
            query: $this->model->newQuery(),
            search: $search,
        );

        // Total de registros que atendem ao filtro
        $filteredCount = $query->count();

        // Ordenação e paginação
        $orderColumn = $this->resolveOrderableColumn(index: $orderIndex);

        $results = $query
            ->orderBy($orderColumn, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        // Transformação dos dados para o frontend
        $data = $results
            ->map(fn (mixed $item): array => $this->transformForDataTables(item: $item))
            ->toArray();

        return [
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredCount,
            'data'            => $data,
        ];
    }

    /**
     * Aplica busca global nas colunas pesquisáveis (OR entre elas).
     *
     * Se o termo de busca estiver vazio ou não houver colunas pesquisáveis,
     * retorna a query sem modificação.
     *
     * @param Builder $query  Query base do Eloquent
     * @param string  $search Termo de busca do DataTables
     *
     * @return Builder Query com filtro aplicado
     */
    protected function applyDataTablesSearch(Builder $query, string $search): Builder
    {
        $searchable = $this->getSearchableColumns();

        if ($search === '' || empty($searchable)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search, $searchable): void {
            foreach ($searchable as $column) {
                $q->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    /**
     * Resolve o nome da coluna ordenável pelo índice do DataTables.
     *
     * O DataTables envia o índice da coluna clicada (0, 1, 2...).
     * Este método mapeia o índice para o nome real da coluna no banco.
     *
     * @param int|string|null $index Índice da coluna (vindo do DataTables)
     *
     * @return string Nome da coluna para ORDER BY
     */
    protected function resolveOrderableColumn(int|string|null $index): string
    {
        $orderable = $this->getOrderableColumns();

        if ($index !== null && isset($orderable[(int) $index])) {
            return $orderable[(int) $index];
        }

        return $this->getDefaultOrderColumn();
    }

    /**
     * Transforma um registro do Model para o formato do DataTables.
     *
     * Implementação padrão retorna todos os atributos via toArray().
     * Sobrescreva este método no repositório concreto para personalizar
     * quais campos e em qual formato serão retornados ao frontend.
     *
     * Exemplo de sobrescrita:
     *
     *     protected function transformForDataTables(mixed $item): array
     *     {
     *         return [
     *             'id'         => $item->id,
     *             'nome'       => $item->nome,
     *             'preco'      => 'R$ ' . number_format($item->preco, 2, ',', '.'),
     *             'criado_em'  => $item->data_criacao?->format('d/m/Y H:i'),
     *         ];
     *     }
     *
     * @param mixed $item Instância do Model Eloquent
     *
     * @return array<string, mixed> Dados formatados do registro
     */
    protected function transformForDataTables(mixed $item): array
    {
        return $item->toArray();
    }

    /**
     * Retorna as colunas pesquisáveis configuradas.
     *
     * @return array<int, string>
     */
    protected function getSearchableColumns(): array
    {
        return property_exists($this, 'searchableColumns')
            ? $this->searchableColumns
            : [];
    }

    /**
     * Retorna as colunas ordenáveis configuradas.
     *
     * @return array<int, string>
     */
    protected function getOrderableColumns(): array
    {
        return property_exists($this, 'orderableColumns')
            ? $this->orderableColumns
            : [];
    }

    /**
     * Retorna a coluna padrão de ordenação.
     */
    protected function getDefaultOrderColumn(): string
    {
        return property_exists($this, 'defaultOrderColumn')
            ? $this->defaultOrderColumn
            : 'id';
    }

    /**
     * Retorna a direção padrão de ordenação.
     */
    protected function getDefaultOrderDirection(): string
    {
        return property_exists($this, 'defaultOrderDirection')
            ? $this->defaultOrderDirection
            : 'desc';
    }
}
