<?php

namespace App\Exceptions;

use Exception;

/**
 * Exceção de negócio da Integration Hub API.
 *
 * Utilizada para erros de regra de negócio que devem ser comunicados
 * ao consumidor da API de forma clara e controlada.
 *
 * Em produção, o exception handler retorna apenas a mensagem e o código,
 * sem expor dados internos do sistema.
 *
 * Exemplo de uso em um Service:
 *
 *     if (!$produto->estaDisponivel()) {
 *         throw new ApiException(
 *             msg: 'Produto indisponível para venda.',
 *             code: Response::HTTP_UNPROCESSABLE_ENTITY,
 *         );
 *     }
 *
 * @see \App\Exceptions\Handler — Trata esta exceção e retorna JSON padronizado
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ApiException extends Exception
{
    /**
     * @param string $msg  Mensagem descritiva do erro de negócio
     * @param int    $code Código HTTP apropriado (padrão: 400 Bad Request)
     */
    public function __construct(
        protected string $msg,
        int $code = 400,
    ) {
        parent::__construct(message: $msg, code: $code);
    }
}
