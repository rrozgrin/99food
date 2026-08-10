<?php

namespace App\Services\Traits;

use App\Services\ResponseApi\ResponseApi;
use App\Services\ResponseApi\ResponseApiDev;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait para padronização de respostas da API.
 *
 * Deve ser utilizada por Controllers, Middleware e qualquer classe
 * que precise montar respostas JSON padronizadas.
 *
 * Comportamento:
 *  - Em produção (APP_ENV=production): retorna ResponseApi (sem dados de debug)
 *  - Em desenvolvimento: retorna ResponseApiDev (com file, line, trace, exception)
 *
 * Exemplo de uso no Controller:
 *
 *     class MeuController extends Controller
 *     {
 *         public function index(MeuService $service): object
 *         {
 *             $dados = $service->listarTodos();
 *             return $this->send(conteudo: $dados);
 *         }
 *     }
 *
 * Exemplo de resposta JSON:
 *     { "conteudo": [...], "msg": "", "code": 200 }
 *
 * @see ResponseApi
 * @see ResponseApiDev
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
trait SendResponse
{
    /**
     * Monta a resposta padrão da API conforme o ambiente.
     *
     * @param  mixed  $conteudo  Dados a serem retornados na resposta
     * @param  int  $code  Código HTTP (padrão: 200)
     * @param  string  $msg  Mensagem descritiva (padrão: vazio)
     * @return ResponseApi|ResponseApiDev Instância da resposta adequada ao ambiente
     */
    protected function send(
        mixed $conteudo = null,
        int $code = Response::HTTP_OK,
        string $msg = '',
    ): ResponseApi|ResponseApiDev {
        if (config('app.env') === 'production') {
            return new ResponseApi(
                conteudo: $conteudo,
                msg: $msg,
                code: $code,
            );
        }

        return new ResponseApiDev(
            conteudo: $conteudo,
            msg: $msg,
            code: $code,
        );
    }
}
