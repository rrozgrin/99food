<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Trait para gerenciamento padronizado de transações de banco de dados nos Services.
 *
 * Garante atomicidade em operações multi-etapa (ex: fechar venda → baixar estoque
 * → lançar financeiro → registrar no caixa).
 *
 * Uso em Services:
 *   class VendasService
 *   {
 *       use WithTransaction;
 *
 *       public function fecharVenda(VendaDTO $dto): Venda
 *       {
 *           return $this->transaction(function () use ($dto) {
 *               $venda   = $this->vendasRepository->create([...]);
 *               $this->estoqueRepository->baixar($venda->itens);
 *               $this->financeiroRepository->lancar($venda);
 *               return $venda;
 *           });
 *       }
 *   }
 */
trait WithTransaction
{
    /**
     * Executa o callback dentro de uma transação de banco de dados.
     *
     * Em caso de ApiException (regra de negócio), faz rollback e relança.
     * Em caso de qualquer outra exceção, faz rollback, loga e relança.
     *
     * @template T
     * @param  Closure(): T  $callback  Operação a executar dentro da transação.
     * @param  int           $attempts  Número de tentativas em caso de deadlock (padrão: 1).
     * @return T
     *
     * @throws ApiException Em caso de erro de regra de negócio.
     * @throws Throwable              Em caso de erro inesperado.
     */
    protected function transaction(Closure $callback, int $attempts = 1): mixed
    {
        try {
            return DB::transaction($callback, $attempts);
        } catch (ApiException $e) {
            // Erros de negócio: relança diretamente (rollback já realizado pelo DB::transaction)
            throw $e;
        } catch (Throwable $e) {
            logger()->error('Falha na transação de banco de dados', [
                'service'   => static::class,
                'exception' => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Executa o callback dentro de uma transação com retry automático em deadlocks.
     *
     * Útil em operações de alta concorrência (ex: atualização de estoque, fechamento de caixa).
     *
     * @template T
     * @param  Closure(): T  $callback  Operação a executar.
     * @param  int           $attempts  Número máximo de tentativas (padrão: 3).
     * @return T
     */
    protected function transactionWithRetry(Closure $callback, int $attempts = 3): mixed
    {
        return $this->transaction($callback, $attempts);
    }
}
