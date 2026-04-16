<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesNameArgument;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Comando Artisan para criação de Repository (Interface + Eloquent + Binding).
 *
 * Gera automaticamente:
 *  1. Interface em app/Repository/Contracts/Models/{Path}/
 *  2. Implementação Eloquent em app/Repository/Eloquent/Models/{Path}/
 *  3. Registra o binding no BindsRepositorios.php
 *
 * Suporta hierarquia de diretórios via namespace:
 *     php artisan make:repository Produto
 *     php artisan make:repository BaseErp\\Produtos\\Produto
 *     php artisan make:repository BaseErp\\Produtos\\Produto --datatables
 *
 * @see \App\Repository\Contracts\RepositoryInterface
 * @see \App\Repository\Contracts\DataTablesInterface
 * @see \App\Repository\Traits\HasDataTables
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class MakeRepositoryCommand extends Command
{
    use ParsesNameArgument;

    protected $signature = 'make:repository
        {name : Nome da entidade (ex: Produto, BaseErp\\\\Produtos\\\\Produto)}
        {--datatables : Incluir suporte a DataTables server-side via HasDataTables}';

    protected $description = 'Cria Interface + Eloquent Repository e registra o binding automaticamente';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    /**
     * Executa o comando.
     */
    public function handle(): int
    {
        $parsed = $this->parseName(rawName: $this->argument('name'));
        $withDataTables = $this->option('datatables');

        $this->createInterface(parsed: $parsed);
        $this->createEloquentRepository(parsed: $parsed, withDataTables: $withDataTables);
        $this->registerBinding(parsed: $parsed);

        $this->newLine();
        $this->components->info("✅ Repository [{$parsed->entityName}] criado com sucesso!");

        return self::SUCCESS;
    }

    /**
     * Cria a interface do repository.
     */
    private function createInterface(object $parsed): void
    {
        $className = "{$parsed->entityName}RepositoryInterface";
        $path = app_path("Repository/Contracts/Models/{$parsed->subDirectory}/{$className}.php");

        if ($this->files->exists($path)) {
            $this->components->warn("Interface já existe: {$path}");
            return;
        }

        $content = str_replace(
            ['{{ entityName }}', '{{ subNamespace }}'],
            [$parsed->entityName, $parsed->subNamespace],
            $this->getInterfaceStub(),
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->components->twoColumnDetail('Interface', $path);
    }

    /**
     * Cria a implementação Eloquent do repository.
     */
    private function createEloquentRepository(object $parsed, bool $withDataTables): void
    {
        $className = "{$parsed->entityName}EloquentRepository";
        $path = app_path("Repository/Eloquent/Models/{$parsed->subDirectory}/{$className}.php");

        if ($this->files->exists($path)) {
            $this->components->warn("Repository já existe: {$path}");
            return;
        }

        $stub = $withDataTables
            ? $this->getEloquentWithDataTablesStub()
            : $this->getEloquentStub();

        $content = str_replace(
            ['{{ entityName }}', '{{ subNamespace }}', '{{ modelUseStatement }}', '{{ entityName_lower }}'],
            [$parsed->entityName, $parsed->subNamespace, $parsed->modelUseStatement, mb_strtolower($parsed->entityName)],
            $stub,
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->components->twoColumnDetail(
            $withDataTables ? 'Repository (DataTables)' : 'Repository',
            $path,
        );
    }

    /**
     * Registra o binding Interface→Eloquent no BindsRepositorios.php.
     */
    private function registerBinding(object $parsed): void
    {
        $bindsPath = app_path('Services/Extensions/BindsRepositorios.php');

        if (! $this->files->exists($bindsPath)) {
            $this->components->error('BindsRepositorios.php não encontrado!');
            return;
        }

        $content = $this->files->get($bindsPath);

        $interfaceFqcn = "App\\Repository\\Contracts\\Models\\{$parsed->subNamespace}\\{$parsed->entityName}RepositoryInterface";

        // Verifica se o binding já está registrado
        if (str_contains($content, $interfaceFqcn)) {
            $this->components->warn('Binding já registrado no BindsRepositorios.');
            return;
        }

        $eloquentFqcn = "App\\Repository\\Eloquent\\Models\\{$parsed->subNamespace}\\{$parsed->entityName}EloquentRepository";

        $binding = "\n        \$app->bind(\n"
            . "            \\{$interfaceFqcn}::class,\n"
            . "            \\{$eloquentFqcn}::class,\n"
            . "        );\n";

        // Insere antes do fechamento do construtor (    }\n})
        $content = str_replace("    }\n}", "{$binding}    }\n}", $content);

        $this->files->put($bindsPath, $content);
        $this->components->twoColumnDetail('Binding', 'BindsRepositorios.php');
    }

    // =========================================================================
    // Stubs
    // =========================================================================

    private function getInterfaceStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Repository\Contracts\Models\{{ subNamespace }};

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato para o repositório de {{ entityName }}.
 *
 * Estende RepositoryInterface que fornece operações CRUD genéricas:
 *   find(), findAll(), findBy(), findOneBy(), paginate(), create(), update(), delete()
 *
 * Adicione aqui APENAS métodos de query específicos deste domínio.
 * Lógica de negócio pertence ao Service — nunca ao Repository.
 *
 * @see RepositoryInterface — Contrato base CRUD
 * @see \App\Repository\Eloquent\Models\{{ subNamespace }}\{{ entityName }}EloquentRepository — Implementação
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
interface {{ entityName }}RepositoryInterface extends RepositoryInterface
{
    // TODO: Adicione aqui métodos específicos do domínio {{ entityName }}.
    //
    // Exemplos:
    //   public function findAtivos(): ?object;
    //   public function findByCategoria(int $categoriaId): ?object;
    //   public function findByPeriodo(string $inicio, string $fim): ?object;
}
STUB;
    }

    private function getEloquentStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\{{ subNamespace }};

use {{ modelUseStatement }};
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\{{ subNamespace }}\{{ entityName }}RepositoryInterface;

// =============================================================================
// Cache automático de leitura — descomente para ativar o CacheableRepository.
//
// Com a trait ativa:
//   - find(), findAll(), paginate() → cacheados automaticamente no Redis
//   - create(), update(), delete() → invalidam o cache automaticamente
//   - Para desabilitar em uma query: $this->repository->withoutCache()->find(1)
//
// ⚠️  Requer driver Redis. Não funciona com driver 'database' ou 'file'.
// =============================================================================
// use App\Repository\Traits\CacheableRepository;

/**
 * Implementação Eloquent do repositório de {{ entityName }}.
 *
 * Herda CRUD genérico do EloquentRepository.
 * Adicione aqui as queries específicas deste domínio.
 *
 * Cache (opt-in):
 *   1. Descomente: use CacheableRepository;
 *   2. Defina: protected int $cacheTtl = 3600;
 *   3. Defina: protected array $cacheTags = ['{{ entityName_lower }}s'];
 *
 * @see {{ entityName }}RepositoryInterface — Contrato
 * @see EloquentRepository — Classe base com CRUD
 * @see \App\Repository\Traits\CacheableRepository — Cache Redis automático (opt-in)
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class {{ entityName }}EloquentRepository extends EloquentRepository
    implements {{ entityName }}RepositoryInterface
{
    // use CacheableRepository; // ← Descomente para ativar cache Redis automático
    //
    // /** TTL do cache em segundos (ex: 3600 = 1 hora) */
    // protected int $cacheTtl = 3600;
    //
    // /** Tags Redis para invalidação em grupo (ex: forgetByTag('{{ entityName_lower }}s')) */
    // protected array $cacheTags = ['{{ entityName_lower }}s'];

    public function __construct({{ entityName }} $model)
    {
        parent::__construct($model);
    }

    // =========================================================================
    // Adicione aqui as queries específicas deste domínio.
    // Consultas genéricas (find, findAll, paginate, create, update, delete)
    // já estão disponíveis via EloquentRepository.
    // =========================================================================

    // /**
    //  * Busca registros ativos de {{ entityName }}.
    //  */
    // public function findAtivos(): ?object
    // {
    //     return $this->model->where('ativo', 'S')->get();
    // }
}
STUB;
    }

    private function getEloquentWithDataTablesStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Repository\Eloquent\Models\{{ subNamespace }};

use {{ modelUseStatement }};
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Traits\HasDataTables;
use App\Repository\Contracts\DataTablesInterface;
use App\Repository\Contracts\Models\{{ subNamespace }}\{{ entityName }}RepositoryInterface;

// =============================================================================
// Cache automático de leitura — descomente para ativar o CacheableRepository.
// =============================================================================
// use App\Repository\Traits\CacheableRepository;

/**
 * Implementação Eloquent do repositório de {{ entityName }}.
 *
 * Herda CRUD genérico + DataTables server-side via trait HasDataTables.
 * A trait é opt-in — adicionada porque este domínio utiliza DataTables.
 *
 * Cache (opt-in):
 *   1. Descomente: use CacheableRepository;
 *   2. Defina: protected int $cacheTtl = 3600;
 *   3. Defina: protected array $cacheTags = ['{{ entityName_lower }}s'];
 *
 * @see {{ entityName }}RepositoryInterface — Contrato
 * @see EloquentRepository — Classe base com CRUD
 * @see HasDataTables — Trait de DataTables server-side
 * @see \App\Repository\Traits\CacheableRepository — Cache Redis automático (opt-in)
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class {{ entityName }}EloquentRepository extends EloquentRepository
    implements {{ entityName }}RepositoryInterface, DataTablesInterface
{
    use HasDataTables;
    // use CacheableRepository; // ← Descomente para ativar cache Redis automático

    // /** TTL do cache em segundos */
    // protected int $cacheTtl = 3600;
    //
    // /** Tags Redis para invalidação em grupo */
    // protected array $cacheTags = ['{{ entityName_lower }}s'];

    /**
     * Colunas onde a busca global do DataTables pesquisa (OR entre elas).
     *
     * TODO: Substitua pelos nomes reais das colunas da tabela.
     *       Exemplo: ['nome', 'descricao', 'codigo']
     */
    protected array $searchableColumns = [];

    /**
     * Colunas ordenáveis — índice corresponde à posição da coluna no frontend.
     *
     * TODO: Substitua pela lista de colunas na mesma ordem do DataTables frontend.
     *       Exemplo: ['id', 'nome', 'preco', 'data_criacao']
     */
    protected array $orderableColumns = [];

    /**
     * Coluna padrão de ordenação.
     */
    protected string $defaultOrderColumn = 'id';

    /**
     * Direção padrão de ordenação.
     */
    protected string $defaultOrderDirection = 'desc';

    public function __construct({{ entityName }} $model)
    {
        parent::__construct($model);
    }

    /**
     * Personaliza a transformação dos dados para o DataTables.
     *
     * Opcional — sobrescreva para customizar a saída.
     * O default da trait retorna toArray() do Model.
     *
     * TODO: Descomente e ajuste conforme os campos do domínio.
     *       Dica: para colunas com MoneyCast, use $item->preco->round(2)->toFloat()
     */
    // protected function transformForDataTables(mixed $item): array
    // {
    //     return [
    //         'id'    => $item->id,
    //         'nome'  => $item->nome,
    //         // 'preco' => 'R$ ' . number_format($item->preco->round(2)->toFloat(), 2, ',', '.'),
    //         // 'ativo' => $item->ativo === 'S' ? 'Ativo' : 'Inativo',
    //     ];
    // }
}
STUB;
    }
}
