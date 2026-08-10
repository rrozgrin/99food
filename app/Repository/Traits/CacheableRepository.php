<?php

declare(strict_types=1);

namespace App\Repository\Traits;

use App\Services\Cache\CacheService;
use Closure;

/**
 * Trait que adiciona cache automático aos métodos de leitura dos Repositories.
 *
 * Ao usar esta trait, os métodos find(), findAll() e findBy() retornam
 * resultados cacheados. Os métodos create(), update() e delete() invalidam
 * automaticamente o cache do domínio.
 *
 * Requer que o Repository defina:
 *   - protected string $cacheDomain — nome do domínio (ex: 'produtos')
 *   - O serviço CacheService injetado via $this->cacheService
 *
 * Em ambiente de testes, o cache é desabilitado automaticamente.
 *
 * Exemplo de uso em um EloquentRepository:
 *
 *   class ProdutosEloquentRepository extends EloquentRepository implements ProdutosRepositoryInterface
 *   {
 *       use CacheableRepository;
 *
 *       protected string $cacheDomain = 'produtos';
 *   }
 */
trait CacheableRepository
{
    protected bool $cacheEnabled = true;

    /**
     * Inicia o cache service — chamar no construtor do repository.
     */
    protected function bootCacheableRepository(): void
    {
        if (app()->environment('testing')) {
            $this->cacheEnabled = false;
        }
    }

    /**
     * Executa um callback com cache aplicado.
     *
     * @param  string  $key  Chave única dentro do domínio.
     * @param  Closure  $callback  Função que retorna os dados do banco.
     * @param  int|null  $ttl  TTL em segundos; usa o padrão do domínio se null.
     */
    protected function cached(string $key, Closure $callback, ?int $ttl = null): mixed
    {
        if (! $this->cacheEnabled) {
            return $callback();
        }

        return $this->getCacheService()->remember(
            key: $key,
            domain: $this->getCacheDomain(),
            callback: $callback,
            ttl: $ttl,
        );
    }

    /**
     * Invalida todo o cache do domínio deste repository.
     * Chamar nos métodos create(), update() e delete().
     */
    protected function invalidateCache(): void
    {
        if ($this->cacheEnabled) {
            $this->getCacheService()->forgetDomain($this->getCacheDomain());
        }
    }

    /**
     * Invalida o cache de múltiplos domínios relacionados.
     * Útil quando uma operação afeta vários bounded contexts.
     *
     * @param  string[]  $domains
     */
    protected function invalidateRelatedDomains(array $domains): void
    {
        if ($this->cacheEnabled) {
            $this->getCacheService()->forgetDomains($domains);
        }
    }

    /**
     * Desabilita o cache para a próxima operação (fluent).
     */
    public function withoutCache(): static
    {
        $clone = clone $this;
        $clone->cacheEnabled = false;

        return $clone;
    }

    // -------------------------------------------------------------------------
    // Helpers — implementar ou injetar no repository concreto
    // -------------------------------------------------------------------------

    /**
     * Retorna o domínio de cache deste repository.
     * Pode ser sobrescrito no repository concreto.
     */
    private function getCacheDomain(): string
    {
        return property_exists($this, 'cacheDomain')
            ? $this->cacheDomain
            : class_basename(static::class);
    }

    /**
     * Retorna a instância do CacheService via container do Laravel.
     */
    private function getCacheService(): CacheService
    {
        return app(CacheService::class);
    }
}
