<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RedisException;
use Throwable;

/**
 * Trait para tratamento de exceções críticas do sistema.
 *
 * Identifica exceções que representam falhas de infraestrutura
 * (banco de dados fora do ar, serviços indisponíveis) e realiza
 * notificações controladas com throttle para evitar flood de alertas.
 *
 * O throttle usa cache para garantir que a mesma mensagem de erro
 * só gere uma notificação a cada TIMEEXPIRED segundos (300s = 5 min).
 *
 * Fallback de cache:
 *  - Tenta usar Redis primeiro
 *  - Se Redis estiver offline, usa cache em arquivo
 *
 * @see AlertaException — Exceção com nível de alerta
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
trait HandlerExceptionCritical
{
    /**
     * Intervalo mínimo entre notificações da mesma mensagem (em segundos).
     */
    private const TIMEEXPIRED = 300;

    /**
     * Verifica se a exceção é crítica e deve gerar notificação.
     *
     * Exceções críticas:
     *  - QueryException com SQLSTATE[HY000] (falha de conexão com o banco)
     *  - AlertaException (alertas explícitos do sistema)
     *
     * @param Throwable $throwable Exceção a ser verificada
     *
     * @return bool True se a exceção foi tratada como crítica
     */
    public function isCritical(Throwable $throwable): bool
    {
        if ($throwable instanceof QueryException) {
            if (preg_match('/SQLSTATE\[HY000\]/', $throwable->getMessage())) {
                $this->notificar(message: 'ERRO DATABASE ' . $throwable->getMessage());
                return true;
            }
        }

        if ($throwable instanceof AlertaException) {
            $this->notificar(
                message: 'ATENÇÃO ' . $throwable->getMessage(),
                nivel: $throwable->getNivel(),
            );
            return true;
        }

        return false;
    }

    /**
     * Envia notificação com throttle para evitar flood de alertas.
     *
     * @param string $message Mensagem de notificação
     * @param string $nivel   Nível de log PSR-3 (padrão: 'emergency')
     */
    private function notificar(string $message, string $nivel = 'emergency'): void
    {
        $cacheKey = md5($message);

        try {
            // Verifica throttle no Redis
            if (Cache::store('redis')->get($cacheKey)) {
                return;
            }

            Cache::store('redis')->put($cacheKey, $message, self::TIMEEXPIRED);
            Log::$nivel($message);
        } catch (RedisException) {
            // Fallback: cache em arquivo quando Redis está offline
            $this->tratarCacheSemRedis(message: $message, nivel: $nivel);
        }
    }

    /**
     * Fallback de notificação quando o Redis está indisponível.
     *
     * Usa o driver de cache em arquivo para manter o throttle
     * e registra o erro adicional do Redis no log.
     *
     * @param string $message Mensagem de notificação
     * @param string $nivel   Nível de log PSR-3
     */
    private function tratarCacheSemRedis(string $message, string $nivel = 'emergency'): void
    {
        $cacheKey = md5($message);

        if (Cache::store('file')->get($cacheKey)) {
            return;
        }

        Cache::store('file')->put($cacheKey, $message, self::TIMEEXPIRED);
        Log::$nivel('ERRO REDIS ' . $message);
    }
}
