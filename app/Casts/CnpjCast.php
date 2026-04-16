<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Cnpj;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast Eloquent para mapear colunas CHAR(14) ou VARCHAR(18) para o VO Cnpj.
 *
 * Uso no model:
 *   protected function casts(): array
 *   {
 *       return [
 *           'cnpj' => CnpjCast::class,
 *       ];
 *   }
 *
 * O banco deve armazenar apenas os 14 dígitos (sem formatação).
 *
 * @implements CastsAttributes<?Cnpj, Cnpj|string|null>
 */
class CnpjCast implements CastsAttributes
{
    /**
     * Converte o valor do banco de dados para o VO Cnpj.
     * Retorna null se o valor for nulo ou inválido (sem lançar exceção).
     *
     * @return ?Cnpj
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Cnpj
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Cnpj::tryOf((string) $value);
    }

    /**
     * Converte o VO Cnpj para string de 14 dígitos antes de persistir no banco.
     *
     * @return ?string
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Cnpj) {
            return $value->digits();
        }

        $cnpj = Cnpj::tryOf((string) $value);

        return $cnpj?->digits();
    }
}
