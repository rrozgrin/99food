<?php

namespace App\Services\Extensions;

/**
 * Interface marcadora para DTOs que podem ser convertidos pelo RequestBodyConverter.
 *
 * Todo DTO que precise ser deserializado a partir do corpo da requisição
 * deve implementar esta interface.
 *
 * Exemplo:
 *
 *     readonly class ProdutoDTO implements RequestBodyConverterInterface
 *     {
 *         public function __construct(
 *             public ?int $id,
 *             public string $nome,
 *             public float $preco,
 *         ) {}
 *     }
 *
 * @see RequestBodyConverter — Serviço que realiza a conversão
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
interface RequestBodyConverterInterface
{
}
