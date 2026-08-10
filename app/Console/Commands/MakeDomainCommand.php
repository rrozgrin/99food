<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesNameArgument;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

/**
 * Comando Artisan para criação de um domínio completo.
 *
 * Gera TODA a estrutura de um novo domínio de uma só vez:
 *  1. Model (app/Models/{Path}/)
 *  2. DTO (app/DTO/{Path}/)
 *  3. Repository Interface + Eloquent + Binding
 *  4. Service (app/Services/{Path}/)
 *  5. Controller (app/Http/Controllers/{Path}/)
 *  6. Sugestão de rotas no terminal
 *
 * Suporta hierarquia de diretórios via namespace:
 *     php artisan make:domain Produto
 *     php artisan make:domain BaseErp\\Produtos\\Produto
 *     php artisan make:domain BaseErp\\Produtos\\Produto --table=webc_produto
 *     php artisan make:domain BaseErp\\Produtos\\Produto --table=webc_produto --datatables
 *
 * @see MakeRepositoryCommand — Cria Repository isolado
 * @see MakeServiceCommand — Cria Service isolado
 * @see MakeDtoCommand — Cria DTO isolado
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class MakeDomainCommand extends Command
{
    use ParsesNameArgument;

    protected $signature = 'make:domain
        {name : Nome da entidade (ex: Produto, BaseErp\\\\Produtos\\\\Produto)}
        {--table= : Nome da tabela no banco (ex: webc_produto). Se começa com webc_, assume timestamps legados}
        {--datatables : Incluir suporte a DataTables no Repository}';

    protected $description = 'Cria a estrutura completa de um domínio: Model, DTO, Repository, Service, Controller e Binding';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    /**
     * Executa o comando criando todos os artefatos do domínio.
     */
    public function handle(): int
    {
        $parsed = $this->parseName(rawName: $this->argument('name'));
        $table = $this->option('table');
        $withDataTables = $this->option('datatables');

        $this->newLine();
        $this->components->info("🏗️  Criando domínio [{$parsed->subNamespace}]...");
        $this->newLine();

        // 1. Model (não tem subcomando — criado aqui)
        $this->createModel(parsed: $parsed, table: $table);

        // 2. DTO — passa o nome original para o subcomando (ele faz seu próprio parsing)
        $originalName = $this->argument('name');
        Artisan::call('make:dto', ['name' => $originalName], $this->output);

        // 3. Repository (Interface + Eloquent + Binding)
        $repoArgs = ['name' => $originalName];
        if ($withDataTables) {
            $repoArgs['--datatables'] = true;
        }
        Artisan::call('make:repository', $repoArgs, $this->output);

        // 4. Service
        Artisan::call('make:service', ['name' => $originalName], $this->output);

        // 5. Controller (com anotações Swagger)
        $this->createController(parsed: $parsed);

        // 6. Registrar Tag no OpenApiSpec.php para o Swagger
        $this->registerSwaggerTag(parsed: $parsed);

        // Resumo final
        $this->newLine();
        $this->components->info("✅ Domínio [{$parsed->subNamespace}] criado com sucesso!");
        $this->newLine();

        // Sugestão de rotas
        $this->showRouteSuggestion(parsed: $parsed);

        return self::SUCCESS;
    }

    /**
     * Cria o Model Eloquent.
     *
     * Se --table começa com 'webc_', assume tabela legada com
     * timestamps em português (data_criacao / data_alteracao).
     *
     * Exemplos de localização:
     *   Produto                       → app/Models/Produto.php (namespace App\Models)
     *   BaseErp\Produtos\Produto → app/Models/BaseErp/Produtos/Produto.php
     *                                     (namespace App\Models\BaseErp\Produtos)
     */
    private function createModel(object $parsed, ?string $table): void
    {
        // Para Model, usamos modelNamespace para determinar o diretório
        // App\Models → Models/    |    App\Models\X\Y → Models/X/Y/
        $modelDir = str_replace('App\\Models', '', $parsed->modelNamespace);
        $modelDir = ltrim(str_replace('\\', '/', $modelDir), '/');

        $path = $modelDir !== ''
            ? app_path("Models/{$modelDir}/{$parsed->entityName}.php")
            : app_path("Models/{$parsed->entityName}.php");

        if ($this->files->exists($path)) {
            $this->components->warn("Model já existe: {$path}");

            return;
        }

        $isLegacy = $table !== null && str_starts_with($table, 'webc_');

        $tableProperty = $table !== null
            ? "\n    /**\n     * Nome da tabela no banco de dados.\n     */\n    protected \$table = '{$table}';\n"
            : '';

        $timestampConstants = $isLegacy
            ? "\n    /**\n     * Nome da coluna de criação (CREATED_AT) — padrão legado.\n     */\n    const CREATED_AT = 'data_criacao';\n\n    /**\n     * Nome da coluna de atualização (UPDATED_AT) — padrão legado.\n     */\n    const UPDATED_AT = 'data_alteracao';\n"
            : '';

        $content = str_replace(
            ['{{ entityName }}', '{{ modelNamespace }}', '{{ tableProperty }}', '{{ timestampConstants }}', '{{ subNamespace }}', '{{ entityName_lower }}'],
            [$parsed->entityName, $parsed->modelNamespace, $tableProperty, $timestampConstants, $parsed->subNamespace, mb_strtolower($parsed->entityName)],
            $this->getModelStub(),
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->components->twoColumnDetail('Model', $path);
    }

    /**
     * Cria o Controller do domínio.
     */
    private function createController(object $parsed): void
    {
        $path = app_path("Http/Controllers/{$parsed->subDirectory}/{$parsed->entityName}Controller.php");

        if ($this->files->exists($path)) {
            $this->components->warn("Controller já existe: {$path}");

            return;
        }

        $routePrefix = mb_strtolower($parsed->entityName).'s';

        $content = str_replace(
            ['{{ entityName }}', '{{ subNamespace }}', '{{ routePrefix }}'],
            [$parsed->entityName, $parsed->subNamespace, $routePrefix],
            $this->getControllerStub(),
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->components->twoColumnDetail('Controller', $path);
    }

    /**
     * Exibe sugestão de rotas para adicionar ao routes/api.php.
     */
    private function showRouteSuggestion(object $parsed): void
    {
        $routePrefix = mb_strtolower($parsed->entityName).'s';
        $controllerPath = str_replace('\\', '\\', $parsed->subNamespace);

        $this->components->warn('📋 Adicione as rotas em routes/api.php (dentro do grupo jwt.verify):');
        $this->newLine();
        $this->line("    <fg=gray>use App\\Http\\Controllers\\{$controllerPath}\\{$parsed->entityName}Controller;</>");
        $this->newLine();
        $this->line("    <fg=gray>Route::group(['prefix' => '{$routePrefix}'], function (): void {</>");
        $this->line("    <fg=gray>    Route::get('/', [{$parsed->entityName}Controller::class, 'index']);</>");
        $this->line("    <fg=gray>    Route::get('/{id}', [{$parsed->entityName}Controller::class, 'show']);</>");
        $this->line("    <fg=gray>    Route::post('/', [{$parsed->entityName}Controller::class, 'store']);</>");
        $this->line("    <fg=gray>    Route::put('/{id}', [{$parsed->entityName}Controller::class, 'update']);</>");
        $this->line("    <fg=gray>    Route::delete('/{id}', [{$parsed->entityName}Controller::class, 'destroy']);</>");
        $this->line('    <fg=gray>});</>');
        $this->newLine();
    }

    /**
     * Registra a Tag OpenAPI no OpenApiSpec.php para o Swagger.
     *
     * Insere um atributo #[OA\Tag(...)] antes da classe OpenApiSpec,
     * permitindo que os endpoints do domínio apareçam agrupados na UI.
     */
    private function registerSwaggerTag(object $parsed): void
    {
        $specPath = app_path('OpenApi/OpenApiSpec.php');

        if (! $this->files->exists($specPath)) {
            $this->components->warn('OpenApiSpec.php não encontrado — tag Swagger não registrada.');

            return;
        }

        $content = $this->files->get($specPath);
        $tagName = $parsed->entityName;

        // Verifica se a tag já existe
        if (str_contains($content, "name: '{$tagName}'")) {
            $this->components->warn("Tag Swagger '{$tagName}' já registrada.");

            return;
        }

        $tag = "#[OA\\Tag(\n"
            ."    name: '{$tagName}',\n"
            ."    description: 'Operações de {$tagName}',\n"
            .")]\n";

        // Insere antes da linha "class OpenApiSpec"
        $content = str_replace(
            'class OpenApiSpec {}',
            $tag.'class OpenApiSpec {}',
            $content,
        );

        $this->files->put($specPath, $content);
        $this->components->twoColumnDetail('Swagger Tag', $tagName);
    }

    // =========================================================================
    // Stubs
    // =========================================================================

    private function getModelStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace {{ modelNamespace }};

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// =============================================================================
// Value Object Casts — descomente APENAS os que esta tabela utilizar.
//
// ⚠️  REGRA: NUNCA use 'decimal:2' para valores monetários.
//          Use sempre MoneyCast::class (BCMath, 8 casas decimais).
//
// ⚠️  COLUNA MONETÁRIA: DECIMAL(15,8) no banco.
//     Suporta até R$ 9.999.999,99999999 com precisão total.
// =============================================================================
// use App\Casts\MoneyCast;       // ← colunas de valor monetário  → DECIMAL(15,8)
// use App\Casts\PercentageCast;  // ← alíquotas fiscais (ICMS, ISS, PIS, COFINS...)
// use App\Casts\QuantityCast;    // ← quantidades de produto/estoque
// use App\Casts\CpfCast;         // ← CPF (valida dígitos verificadores + armazena só dígitos)
// use App\Casts\CnpjCast;        // ← CNPJ (valida dígitos verificadores + armazena só dígitos)
// use App\Casts\PeriodCast;      // ← períodos fiscais → formato "2025-01-01/2025-01-31"

// =============================================================================
// Audit Trail — descomente para rastrear criação/alteração/exclusão na
// tabela webc_auditoria. Não impacta performance (event hooks Eloquent).
// =============================================================================
// use App\Models\Traits\Auditable;

/**
 * Model Eloquent para {{ entityName }}.
 *
 * Convenções obrigatórias:
 *  - Use $fillable — nunca $guarded = []
 *  - Casts via método casts() — nunca property $casts
 *  - Colunas monetárias: MoneyCast + DECIMAL(15,8) no banco
 *  - Tabelas webc_: definir $table + CREATED_AT/UPDATED_AT em português
 *
 * TODO: Defina $fillable, casts() e relacionamentos.
 *
 * @see \App\Repository\Contracts\Models\{{ subNamespace }}\{{ entityName }}RepositoryInterface
 * @see \App\Services\{{ subNamespace }}\{{ entityName }}Service
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class {{ entityName }} extends Model
{{{ tableProperty }}{{ timestampConstants }}
    // =========================================================================
    // Audit Trail — ative adicionando a trait + listando campos sensíveis.
    // =========================================================================
    // use Auditable;
    //
    // /** Campos excluídos do log de auditoria (senhas, tokens, etc.) */
    // protected array $auditExclude = ['senha', 'token_reset', 'remember_token'];

    /**
     * Campos que podem ser preenchidos via mass assignment.
     *
     * TODO: Adicione os campos da tabela.
     *       Dicas de nomenclatura dos casts:
     *
     *         'preco'          => DECIMAL(15,8) + MoneyCast
     *         'aliquota_icms'  => DECIMAL(15,8) + PercentageCast
     *         'quantidade'     => DECIMAL(15,8) + QuantityCast
     *         'cpf'            => CHAR(11)       + CpfCast
     *         'cnpj'           => CHAR(14)       + CnpjCast
     *         'ativo'          => CHAR(1) 'S'/'N' (sem cast)
     *         'data_nasc'      => 'date'
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // 'nome',
        // 'ativo',        // 'S' ou 'N'
        //
        // --- Valores monetários (DECIMAL 15,8 no banco) ---
        // 'preco',
        // 'custo',
        //
        // --- Percentuais fiscais ---
        // 'aliquota_icms',
        // 'desconto_max',
        //
        // --- Quantidades ---
        // 'quantidade',
        //
        // --- Documentos ---
        // 'cpf',          // armazena apenas dígitos (CpfCast faz a formatação)
        // 'cnpj',         // armazena apenas dígitos
        //
        // --- Chaves estrangeiras ---
        // 'categoria_id',
        // 'criado_por',
        // 'atualizado_por',
    ];

    /**
     * Casts de atributos — converte automaticamente entre o banco e PHP.
     *
     * Exemplos de uso:
     *
     *   Monetário  → 'preco'          => MoneyCast::class
     *   Percentual → 'aliquota_icms'  => PercentageCast::class
     *   Quantidade → 'quantidade'     => QuantityCast::class
     *   Qtd (3dec) → 'peso_kg'        => QuantityCast::class . ':3'
     *   CPF        → 'cpf'            => CpfCast::class
     *   CNPJ       → 'cnpj'           => CnpjCast::class
     *   Período    → 'periodo'        => PeriodCast::class
     *   Data       → 'data_nasc'      => 'date'
     *   DateTime   → 'data_criacao'   => 'datetime'
     *   Boolean    → 'ativo'          => 'boolean'  (se coluna for tinyint 0/1)
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // TODO: Adicione os casts das colunas desta entidade.
            //
            // 'preco'          => MoneyCast::class,
            // 'aliquota_icms'  => PercentageCast::class,
            // 'quantidade'     => QuantityCast::class,
            // 'cpf'            => CpfCast::class,
            // 'cnpj'           => CnpjCast::class,
            // 'data_nasc'      => 'date',
        ];
    }

    // =========================================================================
    // Relacionamentos — descomente e adapte conforme as FKs desta tabela.
    // =========================================================================

    // /**
    //  * Categoria à qual este registro pertence.
    //  */
    // public function categoria(): BelongsTo
    // {
    //     return $this->belongsTo(Categoria::class, 'categoria_id');
    // }

    // /**
    //  * Itens vinculados a este registro.
    //  */
    // public function itens(): HasMany
    // {
    //     return $this->hasMany(Item::class, '{{ entityName_lower }}_id');
    // }
}
STUB;
    }

    private function getControllerStub(): string
    {
        return <<<'STUB'
<?php

namespace App\Http\Controllers\{{ subNamespace }};

use App\Http\Controllers\Controller;
use App\Services\{{ subNamespace }}\{{ entityName }}Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

/**
 * Controller de {{ entityName }}.
 *
 * Enxuto — apenas recebe a request, delega para o Service
 * e retorna a resposta padronizada via $this->send().
 *
 * @see {{ entityName }}Service — Lógica de negócio
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
// TODO: Descomente a tag abaixo e ajuste o nome para agrupar no Swagger.
// #[OA\Tag(name: '{{ entityName }}', description: 'Operações de {{ entityName }}')]
class {{ entityName }}Controller extends Controller
{
    public function __construct(
        private readonly {{ entityName }}Service $service,
    ) {}

    /**
     * Lista todos os registros de {{ entityName }}.
     */
    #[OA\Get(
        path: '/{{ routePrefix }}',
        summary: 'Listar {{ entityName }}',
        description: 'Retorna todos os registros de {{ entityName }}.',
        tags: ['{{ entityName }}'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista retornada com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaPadrao'),
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            data: $this->send(conteudo: $this->service->listar()),
        );
    }

    /**
     * Busca registro de {{ entityName }} por ID.
     */
    #[OA\Get(
        path: '/{{ routePrefix }}/{id}',
        summary: 'Buscar {{ entityName }} por ID',
        description: 'Retorna um único registro de {{ entityName }} pelo ID informado.',
        tags: ['{{ entityName }}'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID do registro',
                schema: new OA\Schema(type: 'integer', example: 1),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Registro encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaPadrao'),
            ),
            new OA\Response(response: 404, description: 'Registro não encontrado'),
        ],
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(
            data: $this->send(conteudo: $this->service->buscarPorId(id: $id)),
        );
    }

    /**
     * Cria um novo registro de {{ entityName }}.
     *
     * TODO: Descomente quando o método criar() do Service estiver pronto.
     *       Ajuste as propriedades do requestBody conforme os campos reais.
     */
    // #[OA\Post(
    //     path: '/{{ routePrefix }}',
    //     summary: 'Criar {{ entityName }}',
    //     description: 'Cria um novo registro de {{ entityName }}.',
    //     tags: ['{{ entityName }}'],
    //     security: [['bearerAuth' => []]],
    //     requestBody: new OA\RequestBody(
    //         required: true,
    //         content: new OA\JsonContent(
    //             required: ['nome'],
    //             properties: [
    //                 new OA\Property(property: 'nome', type: 'string', example: 'Exemplo'),
    //                 // TODO: Adicione mais propriedades conforme os campos da entidade
    //             ],
    //         ),
    //     ),
    //     responses: [
    //         new OA\Response(response: 201, description: 'Criado com sucesso'),
    //         new OA\Response(response: 422, description: 'Erro de validação'),
    //     ],
    // )]
    // public function store(): JsonResponse
    // {
    //     return response()->json(
    //         data: $this->send(
    //             conteudo: $this->service->criar(),
    //             code: Response::HTTP_CREATED,
    //             msg: '{{ entityName }} criado com sucesso.',
    //         ),
    //         status: Response::HTTP_CREATED,
    //     );
    // }

    /**
     * Atualiza um registro existente de {{ entityName }}.
     *
     * TODO: Descomente quando o método atualizar() do Service estiver pronto.
     *       Ajuste as propriedades do requestBody conforme os campos reais.
     */
    // #[OA\Put(
    //     path: '/{{ routePrefix }}/{id}',
    //     summary: 'Atualizar {{ entityName }}',
    //     description: 'Atualiza um registro existente de {{ entityName }} pelo ID.',
    //     tags: ['{{ entityName }}'],
    //     security: [['bearerAuth' => []]],
    //     parameters: [
    //         new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    //     ],
    //     requestBody: new OA\RequestBody(
    //         required: true,
    //         content: new OA\JsonContent(
    //             properties: [
    //                 new OA\Property(property: 'nome', type: 'string', example: 'Atualizado'),
    //                 // TODO: Adicione mais propriedades conforme os campos da entidade
    //             ],
    //         ),
    //     ),
    //     responses: [
    //         new OA\Response(response: 200, description: 'Atualizado com sucesso'),
    //         new OA\Response(response: 404, description: 'Não encontrado'),
    //     ],
    // )]
    // public function update(int $id): JsonResponse
    // {
    //     return response()->json(
    //         data: $this->send(
    //             conteudo: $this->service->atualizar(id: $id),
    //             msg: '{{ entityName }} atualizado com sucesso.',
    //         ),
    //     );
    // }

    /**
     * Remove um registro de {{ entityName }}.
     *
     * TODO: Descomente quando o método remover() do Service estiver pronto.
     */
    // #[OA\Delete(
    //     path: '/{{ routePrefix }}/{id}',
    //     summary: 'Remover {{ entityName }}',
    //     description: 'Remove um registro de {{ entityName }} pelo ID.',
    //     tags: ['{{ entityName }}'],
    //     security: [['bearerAuth' => []]],
    //     parameters: [
    //         new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    //     ],
    //     responses: [
    //         new OA\Response(response: 200, description: 'Removido com sucesso'),
    //         new OA\Response(response: 404, description: 'Não encontrado'),
    //     ],
    // )]
    // public function destroy(int $id): JsonResponse
    // {
    //     return response()->json(
    //         data: $this->send(
    //             conteudo: $this->service->remover(id: $id),
    //             msg: '{{ entityName }} removido com sucesso.',
    //         ),
    //     );
    // }
}
STUB;
    }
}
