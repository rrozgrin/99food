<?php

namespace App\Services\ResponseApi;

use App\Services\Traits\SendResponse;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Resposta da API para ambiente de desenvolvimento.
 *
 * Estende ResponseApi adicionando campos de debug que facilitam
 * a identificação de erros durante o desenvolvimento:
 *
 * {
 *     "conteudo": mixed,
 *     "msg": string,
 *     "code": int,
 *     "file": string,       // Arquivo onde ocorreu o erro
 *     "line": int,           // Linha do erro
 *     "exception": string,   // Classe da exceção (ex: "App\Exceptions\ApiException")
 *     "trace": string        // Stack trace completo
 * }
 *
 * Esta classe é utilizada SOMENTE quando APP_ENV !== 'production'.
 * Nunca deve expor informações internas em produção.
 *
 * @see ResponseApi
 * @see SendResponse
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ResponseApiDev extends ResponseApi implements JsonSerializable
{
    /** Caminho do arquivo onde o erro ocorreu */
    protected string $file = '';

    /** Classe completa da exceção (FQCN) */
    protected string $exception = '';

    /** Número da linha onde o erro ocorreu */
    protected int $line = 0;

    /** Stack trace completo como string */
    protected string $trace = '';

    /**
     * Cria uma nova instância de resposta de desenvolvimento.
     *
     * Pode ser construída de duas formas:
     * 1. Com um Throwable — extrai automaticamente todos os dados de debug
     * 2. Sem Throwable — funciona como ResponseApi com campos extras vazios
     *
     * @param  Throwable|null  $throwable  Exceção para extrair dados de debug
     * @param  mixed  $conteudo  Dados da resposta
     * @param  string  $msg  Mensagem descritiva
     * @param  int  $code  Código HTTP
     */
    public function __construct(
        ?Throwable $throwable = null,
        mixed $conteudo = null,
        string $msg = '',
        int $code = Response::HTTP_OK,
    ) {
        if ($throwable !== null) {
            parent::__construct(
                conteudo: null,
                msg: $throwable->getMessage(),
                code: is_int($throwable->getCode()) ? $throwable->getCode() : 0,
            );

            $this->file = $throwable->getFile();
            $this->line = $throwable->getLine();
            $this->trace = $throwable->getTraceAsString();
            $this->exception = $throwable::class;

            // Se a exceção tiver método getConteudo(), utiliza o conteúdo dela
            if (method_exists($throwable, 'getConteudo')) {
                $this->conteudo = $throwable->getConteudo();
            }
        } else {
            parent::__construct(
                conteudo: $conteudo,
                msg: $msg,
                code: $code,
            );
        }
    }

    /**
     * Serializa o objeto para JSON incluindo campos de debug.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'file' => $this->file,
            'line' => $this->line,
            'exception' => $this->exception,
            'trace' => $this->trace,
        ];
    }

    /**
     * Obtém o caminho do arquivo do erro.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Obtém o número da linha do erro.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Obtém o stack trace.
     */
    public function getTrace(): string
    {
        return $this->trace;
    }

    /**
     * Obtém o nome completo da classe da exceção.
     */
    public function getException(): string
    {
        return $this->exception;
    }
}
