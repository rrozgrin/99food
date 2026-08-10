<?php

namespace App\Services\ResponseApi;

use App\Services\Traits\SendResponse;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta padrão da API para ambiente de produção.
 *
 * Estrutura JSON retornada:
 * {
 *     "conteudo": mixed,   // Dados da resposta (objeto, array, string, null)
 *     "msg": string,       // Mensagem descritiva (sucesso ou erro)
 *     "code": int          // Código HTTP da resposta
 * }
 *
 * Utilizada pela trait SendResponse e pelo exception handler.
 * Em desenvolvimento, a classe ResponseApiDev estende esta adicionando
 * campos de debug (file, line, trace, exception).
 *
 * @see ResponseApiDev
 * @see SendResponse
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ResponseApi implements JsonSerializable
{
    /**
     * Cria uma nova instância de resposta da API.
     *
     * @param  mixed  $conteudo  Dados da resposta (objeto, array, string, null)
     * @param  string  $msg  Mensagem descritiva para o consumidor da API
     * @param  int  $code  Código HTTP da resposta (padrão: 200)
     */
    public function __construct(
        protected mixed $conteudo = null,
        protected string $msg = '',
        protected int $code = Response::HTTP_OK,
    ) {}

    /**
     * Serializa o objeto para JSON.
     *
     * Retorna todos os atributos públicos e protegidos como array associativo.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'conteudo' => $this->conteudo,
            'msg' => $this->msg,
            'code' => $this->code,
        ];
    }

    /**
     * Obtém a mensagem da resposta.
     */
    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * Define a mensagem da resposta.
     */
    public function setMsg(string $msg): static
    {
        $this->msg = $msg;

        return $this;
    }

    /**
     * Obtém o código HTTP da resposta.
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Define o código HTTP da resposta.
     */
    public function setCode(int $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Obtém o conteúdo/dados da resposta.
     */
    public function getConteudo(): mixed
    {
        return $this->conteudo;
    }

    /**
     * Define o conteúdo/dados da resposta.
     */
    public function setConteudo(mixed $conteudo): static
    {
        $this->conteudo = $conteudo;

        return $this;
    }
}
