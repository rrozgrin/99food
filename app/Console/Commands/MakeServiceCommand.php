<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesNameArgument;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Comando Artisan para criação de Service.
 *
 * Gera um Service com injeção automática do Repository, UsuarioLogadoService
 * e RequestBodyConverter correspondentes ao domínio.
 *
 * Suporta hierarquia de diretórios via namespace:
 *     php artisan make:service Produto
 *     php artisan make:service BaseErp\\Produtos\\Produto
 *
 * @see \App\Services\Auth\UsuarioLogadoService
 * @see \App\Services\Extensions\RequestBodyConverter
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class MakeServiceCommand extends Command
{
    use ParsesNameArgument;

    protected $signature = 'make:service
        {name : Nome da entidade (ex: Produto, BaseErp\\\\Produtos\\\\Produto)}';

    protected $description = 'Cria um Service com injeção do Repository, UsuarioLogadoService e RequestBodyConverter';

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
        $path = app_path("Services/{$parsed->subDirectory}/{$parsed->entityName}Service.php");

        if ($this->files->exists($path)) {
            $this->components->error("Service já existe: {$path}");
            return self::FAILURE;
        }

        $content = str_replace(
            ['{{ entityName }}', '{{ subNamespace }}', '{{ entityName_lower }}'],
            [$parsed->entityName, $parsed->subNamespace, mb_strtolower($parsed->entityName)],
            $this->getStub(),
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->components->twoColumnDetail('Service', $path);

        return self::SUCCESS;
    }

    // =========================================================================
    // Stub
    // =========================================================================

    private function getStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Services\{{ subNamespace }};

use App\DTO\{{ subNamespace }}\{{ entityName }}DTO;
use App\Exceptions\ApiException;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Extensions\RequestBodyConverter;
use App\Services\Traits\WithTransaction;
use App\Repository\Contracts\Models\{{ subNamespace }}\{{ entityName }}RepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

// =============================================================================
// Serviços opcionais — importe apenas os que este domínio utilizar.
// =============================================================================
// use App\Services\Fiscal\FiscalCalculatorService; // ← cálculos de desconto, imposto, pro-rata
// use App\Services\Cache\CacheService;             // ← cache Redis com tags por domínio
// use App\ValueObjects\Money;                      // ← valores monetários (BCMath)
// use App\ValueObjects\Percentage;                 // ← alíquotas fiscais

// =============================================================================
// Domain Events — importe e crie o evento correspondente ao criar/alterar.
// Exemplo: app/Events/Domain/{{ entityName }}/{{ entityName }}CriadoEvent.php
// =============================================================================
// use App\Events\Domain\{{ subNamespace }}\{{ entityName }}CriadoEvent;
// use App\Events\Domain\{{ subNamespace }}\{{ entityName }}AtualizadoEvent;

/**
 * Serviço de lógica de negócio para {{ entityName }}.
 *
 * TODA regra de negócio de {{ entityName }} deve ficar nesta classe.
 * Controllers e Repositories NÃO devem conter lógica de negócio.
 *
 * A trait WithTransaction fornece:
 *   - $this->transaction(fn() => ...) — bloco atômico com rollback automático
 *   - $this->transactionWithRetry(fn() => ..., attempts: 3) — retry em deadlocks
 *
 * @see {{ entityName }}RepositoryInterface — Repositório de dados
 * @see \App\Http\Controllers\{{ subNamespace }}\{{ entityName }}Controller — Controller
 * @see \App\Services\Traits\WithTransaction — Gerenciamento de transações DB
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class {{ entityName }}Service
{
    use WithTransaction;

    public function __construct(
        private readonly {{ entityName }}RepositoryInterface $repository,
        private readonly UsuarioLogadoService $usuarioLogado,
        private readonly RequestBodyConverter $converter,
        // private readonly FiscalCalculatorService $fiscal, // ← descomente se usar cálculos fiscais
        // private readonly CacheService $cache,             // ← descomente se usar cache Redis por domínio
    ) {}

    /**
     * Lista registros com paginação.
     *
     * @param int $perPage Itens por página
     */
    public function listar(int $perPage = 15): mixed
    {
        return $this->repository->paginate(perPage: $perPage);
    }

    /**
     * Busca um registro pelo ID.
     *
     * @param int $id Identificador do registro
     *
     * @throws ApiException Se não encontrado
     */
    public function buscarPorId(int $id): object
    {
        $registro = $this->repository->find(id: $id);

        if ($registro === null) {
            throw new ApiException(
                msg: '{{ entityName }} não encontrado.',
                code: Response::HTTP_NOT_FOUND,
            );
        }

        return $registro;
    }

    /**
     * Cria um novo registro a partir do body da request.
     *
     * TODO: Descomente o método e mapeie os campos do DTO.
     *
     * Padrão obrigatório:
     *  - Use $this->transaction() quando alterar MAIS DE UMA tabela.
     *  - Dispare um DomainEvent ao final para notificar outros domínios.
     *  - Para cálculos monetários use $this->fiscal (FiscalCalculatorService).
     *
     * @return object Registro criado
     */
    // public function criar(): object
    // {
    //     $dto = $this->converter->deserialize(new {{ entityName }}DTO());
    //
    //     // Use $this->transaction() para garantir atomicidade em múltiplas tabelas.
    //     // Para operação em tabela única, o transaction é opcional.
    //     return $this->transaction(function () use ($dto) {
    //         $registro = $this->repository->create([
    //             // TODO: Mapeie os campos do DTO.
    //             // Para campos monetários: não passe float — passe string ou Money VO.
    //             // 'nome'       => $dto->nome,
    //             // 'preco'      => $dto->preco,     // ← string '29.90' ou Money::of($dto->preco)
    //             // 'criado_por' => $this->usuarioLogado->getId(),
    //         ]);
    //
    //         // Invalide o cache do domínio após gravar (se usar CacheableRepository):
    //         // $this->cache->forgetByTag('{{ entityName_lower }}s');
    //
    //         // Dispare um domain event para notificar outros domínios:
    //         // event(new {{ entityName }}CriadoEvent(registroId: $registro->id));
    //
    //         return $registro;
    //     });
    // }

    /**
     * Atualiza um registro existente.
     *
     * TODO: Descomente e mapeie os campos do DTO.
     *
     * @param int $id Identificador do registro
     *
     * @throws ApiException Se não encontrado
     */
    // public function atualizar(int $id): bool
    // {
    //     $this->buscarPorId(id: $id);
    //     $dto = $this->converter->deserialize(new {{ entityName }}DTO());
    //
    //     return $this->transaction(function () use ($id, $dto) {
    //         $resultado = $this->repository->update(
    //             data: [
    //                 // TODO: Mapeie os campos do DTO.
    //                 // 'nome'          => $dto->nome,
    //                 // 'atualizado_por' => $this->usuarioLogado->getId(),
    //             ],
    //             id: $id,
    //         );
    //
    //         // Invalide o cache do registro específico após atualizar:
    //         // $this->cache->forget("{{ entityName_lower }}:{$id}");
    //
    //         // Dispare domain event se outros domínios precisarem saber da atualização:
    //         // event(new {{ entityName }}AtualizadoEvent(registroId: $id));
    //
    //         return $resultado;
    //     });
    // }

    /**
     * Remove um registro pelo ID.
     *
     * TODO: Descomente quando estiver pronto.
     *
     * @param int $id Identificador do registro
     *
     * @throws ApiException Se não encontrado
     */
    // public function remover(int $id): bool
    // {
    //     $this->buscarPorId(id: $id);
    //     return $this->repository->delete(id: $id);
    // }
}
STUB;
    }
}
