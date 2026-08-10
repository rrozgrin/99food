<?php

namespace App\Exceptions;

use Exception;

/**
 * Exceção de alerta com nível de severidade.
 *
 * Utilizada para erros que precisam de notificação especial
 * (ex: banco de dados fora do ar, serviço externo indisponível).
 *
 * O nível determina a severidade do alerta e é utilizado
 * pela trait HandlerExceptionCritical para logging.
 *
 * Níveis suportados (compatíveis com PSR-3 LogLevel):
 *  - 'emergency' — Sistema inutilizável
 *  - 'alert'     — Ação imediata necessária
 *  - 'critical'  — Condições críticas
 *  - 'error'     — Erros que precisam de atenção
 *  - 'warning'   — Situações anormais mas não críticas
 *
 * Exemplo de uso:
 *
 *     throw new AlertaException(
 *         msg: 'Serviço de pagamento indisponível',
 *         nivel: 'critical',
 *     );
 *
 * @see HandlerExceptionCritical — Trait que processa esta exceção
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class AlertaException extends Exception
{
    /**
     * @param  string  $msg  Mensagem descritiva do alerta
     * @param  string  $nivel  Nível de severidade (PSR-3 LogLevel)
     */
    public function __construct(
        string $msg,
        protected readonly string $nivel = 'emergency',
    ) {
        parent::__construct(message: $msg);
    }

    /**
     * Obtém o nível de severidade do alerta.
     */
    public function getNivel(): string
    {
        return $this->nivel;
    }
}
