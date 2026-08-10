<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Closure;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Serviço centralizado de cache para o ERP.
 *
 * Encapsula o cache do Laravel com TTLs por domínio, prefixos padronizados
 * e suporte a cache tags para invalidação eficiente por bounded context.
 *
 * Requer Redis como driver de cache (suporte a tags).
 */
class CacheService
{
    /**
     * Retorna um valor do cache ou executa o callback e armazena o resultado.
     *
     * @param  string  $key  Chave única dentro do domínio.
     * @param  string  $domain  Domínio do ERP (ex: 'clientes', 'produtos').
     * @param  Closure  $callback  Função que retorna o valor a ser cacheado.
     * @param  int|null  $ttl  TTL em segundos; usa TTL padrão do domínio se null.
     */
    public function remember(string $key, string $domain, Closure $callback, ?int $ttl = null): mixed
    {
        $ttl ??= $this->ttlForDomain($domain);
        $cacheKey = $this->buildKey($domain, $key);

        if ($this->supportsTags()) {
            return $this->store()->tags([$domain])->remember($cacheKey, $ttl, $callback);
        }

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Armazena um valor no cache sob um domínio.
     */
    public function put(string $key, string $domain, mixed $value, ?int $ttl = null): void
    {
        $ttl ??= $this->ttlForDomain($domain);
        $cacheKey = $this->buildKey($domain, $key);

        if ($this->supportsTags()) {
            $this->store()->tags([$domain])->put($cacheKey, $value, $ttl);

            return;
        }

        Cache::put($cacheKey, $value, $ttl);
    }

    /**
     * Remove uma chave específica do cache.
     */
    public function forget(string $key, string $domain): void
    {
        $cacheKey = $this->buildKey($domain, $key);

        if ($this->supportsTags()) {
            $this->store()->tags([$domain])->forget($cacheKey);

            return;
        }

        Cache::forget($cacheKey);
    }

    /**
     * Invalida TODO o cache de um domínio (ex: ao salvar um produto, invalida 'produtos').
     *
     * Requer Redis — não funciona com drivers sem suporte a tags.
     */
    public function forgetDomain(string $domain): void
    {
        if ($this->supportsTags()) {
            $this->store()->tags([$domain])->flush();
        }
    }

    /**
     * Invalida o cache de múltiplos domínios de uma só vez.
     *
     * Útil em operações que afetam múltiplos bounded contexts
     * (ex: fechar venda → invalidar 'vendas', 'estoque', 'financeiro').
     *
     * @param  string[]  $domains
     */
    public function forgetDomains(array $domains): void
    {
        foreach ($domains as $domain) {
            $this->forgetDomain($domain);
        }
    }

    /**
     * Verifica se uma chave existe no cache.
     */
    public function has(string $key, string $domain): bool
    {
        $cacheKey = $this->buildKey($domain, $key);

        if ($this->supportsTags()) {
            return $this->store()->tags([$domain])->has($cacheKey);
        }

        return Cache::has($cacheKey);
    }

    // -------------------------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------------------------

    /**
     * Constrói a chave padronizada: {domain}:{key}
     */
    private function buildKey(string $domain, string $key): string
    {
        return "{$domain}:{$key}";
    }

    /**
     * Retorna o TTL configurado para um domínio específico do ERP.
     */
    private function ttlForDomain(string $domain): int
    {
        $ttls = config('cache.ttl', []);

        return (int) ($ttls[$domain] ?? $ttls['default'] ?? 600);
    }

    /**
     * Verifica se o driver de cache atual suporta tags (ex: Redis).
     */
    private function supportsTags(): bool
    {
        try {
            Cache::tags(['__test__']);

            return true;
        } catch (\BadMethodCallException) {
            return false;
        }
    }

    /**
     * Retorna a instância do store de cache configurado.
     */
    private function store(): CacheRepository
    {
        return Cache::store(config('cache.default'));
    }
}
