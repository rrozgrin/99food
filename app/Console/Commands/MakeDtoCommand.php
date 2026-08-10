<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesNameArgument;
use App\Services\Extensions\RequestBodyConverter;
use App\Services\Extensions\RequestBodyConverterInterface;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Comando Artisan para criação de DTO.
 *
 * Gera uma readonly class com RequestBodyConverterInterface,
 * pronta para ser usada com o RequestBodyConverter.
 *
 * Suporta hierarquia de diretórios via namespace:
 *     php artisan make:dto Produto
 *     php artisan make:dto BaseErp\\Produtos\\Produto
 *
 * @see RequestBodyConverter
 * @see RequestBodyConverterInterface
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class MakeDtoCommand extends Command
{
    use ParsesNameArgument;

    protected $signature = 'make:dto
        {name : Nome da entidade (ex: Produto, BaseErp\\\\Produtos\\\\Produto)}';

    protected $description = 'Cria um DTO readonly com RequestBodyConverterInterface';

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
        $path = app_path("DTO/{$parsed->subDirectory}/{$parsed->entityName}DTO.php");

        if ($this->files->exists($path)) {
            $this->components->error("DTO já existe: {$path}");

            return self::FAILURE;
        }

        $content = str_replace(
            ['{{ entityName }}', '{{ subNamespace }}'],
            [$parsed->entityName, $parsed->subNamespace],
            $this->getStub(),
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->components->twoColumnDetail('DTO', $path);

        return self::SUCCESS;
    }

    // =========================================================================
    // Stub
    // =========================================================================

    private function getStub(): string
    {
        return <<<'STUB'
<?php

namespace App\DTO\{{ subNamespace }};

use App\Services\Extensions\RequestBodyConverterInterface;
use OpenApi\Attributes as OA;

/**
 * DTO para transporte de dados de {{ entityName }}.
 *
 * Classe readonly com propriedades tipadas que correspondem
 * às chaves do JSON enviado no body da request.
 *
 * Uso com RequestBodyConverter:
 *     $dto = $this->converter->deserialize(new {{ entityName }}DTO());
 *
 * @see RequestBodyConverterInterface — Interface marcadora
 * @see \App\Services\Extensions\RequestBodyConverter — Conversor JSON→DTO
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
#[OA\Schema(
    schema: '{{ entityName }}DTO',
    title: '{{ entityName }}',
    description: 'Dados de {{ entityName }} para criação/atualização.',
    required: ['id'],
)]
readonly class {{ entityName }}DTO implements RequestBodyConverterInterface
{
    public function __construct(
        #[OA\Property(description: 'ID do registro (null para criação)', example: 1)]
        public ?int $id = null,
        // TODO: Adicione as propriedades do DTO conforme os campos da entidade.
        //       Use #[OA\Property()] para documentar cada campo no Swagger.
        //
        // Exemplo:
        // #[OA\Property(description: 'Nome do registro', example: 'Produto A')]
        // public string $nome = '',
        //
        // #[OA\Property(description: 'Preço unitário', example: 29.90)]
        // public float $preco = 0.0,
        //
        // #[OA\Property(description: 'Status ativo (S/N)', example: 'S')]
        // public string $ativo = 'S',
    ) {}
}
STUB;
    }
}
