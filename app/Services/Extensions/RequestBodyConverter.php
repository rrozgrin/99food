<?php

namespace App\Services\Extensions;

use ReflectionClass;
use ReflectionNamedType;
use Illuminate\Http\Request;

/**
 * Serviço de conversão de request body JSON para DTOs tipados.
 *
 * Substitui o JMS Serializer (descontinuado para PHP 8.5+) por uma
 * solução nativa usando reflection do PHP para mapear automaticamente
 * os campos do JSON para as propriedades do DTO.
 *
 * O DTO deve:
 *  1. Implementar RequestBodyConverterInterface
 *  2. Ter propriedades tipadas com nomes que correspondam às chaves do JSON
 *  3. Preferencialmente ser uma readonly class (PHP 8.2+)
 *
 * Exemplo de uso:
 *
 *     // DTO
 *     readonly class ProdutoDTO implements RequestBodyConverterInterface
 *     {
 *         public function __construct(
 *             public ?int $id,
 *             public string $nome,
 *             public float $preco,
 *         ) {}
 *     }
 *
 *     // No Service
 *     class ProdutoService
 *     {
 *         public function __construct(
 *             private readonly RequestBodyConverter $converter,
 *         ) {}
 *
 *         public function criar(): Produto
 *         {
 *             $dto = $this->converter->deserialize(new ProdutoDTO());
 *             // $dto->nome, $dto->preco estão preenchidos com os dados do request
 *         }
 *     }
 *
 * @see RequestBodyConverterInterface — Interface que os DTOs devem implementar
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class RequestBodyConverter
{
    /**
     * @param Request $request Requisição HTTP injetada pelo container
     */
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * Deserializa o corpo da requisição JSON para o DTO informado.
     *
     * Mapeia cada chave do JSON para a propriedade correspondente do DTO.
     * Propriedades que não existirem no JSON serão mantidas com seu valor
     * padrão (ou null, se nullable).
     *
     * @template T of RequestBodyConverterInterface
     *
     * @param T $dto Instância do DTO (usado para determinar a classe)
     *
     * @return T Nova instância do DTO preenchida com os dados do request
     */
    public function deserialize(RequestBodyConverterInterface $dto): RequestBodyConverterInterface
    {
        $data = $this->request->json()->all();
        return $this->hydrate(className: $dto::class, data: $data);
    }

    /**
     * Deserializa uma string JSON customizada para o DTO informado.
     *
     * Útil quando os dados não vêm diretamente do request body
     * (ex: campo JSON dentro de outro objeto, fila, etc.).
     *
     * @template T of RequestBodyConverterInterface
     *
     * @param T      $dto          Instância do DTO
     * @param string $jsonConteudo String JSON a ser deserializada
     *
     * @return T Nova instância do DTO preenchida
     */
    public function deserializeConteudo(
        RequestBodyConverterInterface $dto,
        string $jsonConteudo,
    ): RequestBodyConverterInterface {
        $data = json_decode($jsonConteudo, associative: true, flags: JSON_THROW_ON_ERROR);
        return $this->hydrate(className: $dto::class, data: $data);
    }

    /**
     * Serializa um objeto para string JSON.
     *
     * @param object $object Objeto a ser serializado
     *
     * @return string JSON do objeto
     */
    public function serialize(object $object): string
    {
        return json_encode(
            value: $object,
            flags: JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Hidrata uma classe DTO com os dados fornecidos via reflection.
     *
     * Utiliza o construtor do DTO para criar a instância, mapeando
     * os parâmetros do construtor com as chaves do array de dados.
     *
     * @param class-string $className FQCN da classe DTO
     * @param array<string, mixed> $data Dados para hidratação
     *
     * @return RequestBodyConverterInterface Instância hidratada do DTO
     */
    private function hydrate(string $className, array $data): RequestBodyConverterInterface
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        // Se o DTO não tem construtor, cria instância vazia e seta propriedades
        if ($constructor === null) {
            $instance = $reflection->newInstance();
            foreach ($data as $key => $value) {
                if ($reflection->hasProperty($key)) {
                    $prop = $reflection->getProperty($key);
                    $prop->setAccessible(true);
                    $prop->setValue($instance, $this->castValue(
                        value: $value,
                        type: $prop->getType(),
                    ));
                }
            }
            return $instance;
        }

        // Monta argumentos na ordem dos parâmetros do construtor
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $data)) {
                $args[] = $this->castValue(
                    value: $data[$name],
                    type: $param->getType(),
                );
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                $args[] = null;
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Converte o valor para o tipo esperado pela propriedade/parâmetro.
     *
     * @param mixed                    $value Valor a ser convertido
     * @param ReflectionNamedType|null $type  Tipo esperado (via reflection)
     *
     * @return mixed Valor convertido
     */
    private function castValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($value === null || $type === null || !($type instanceof ReflectionNamedType)) {
            return $value;
        }

        return match ($type->getName()) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'string' => (string) $value,
            'bool'   => (bool) $value,
            'array'  => (array) $value,
            default  => $value,
        };
    }
}
