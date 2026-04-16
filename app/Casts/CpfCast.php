<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Cpf;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast Eloquent para mapear colunas CHAR(11) ou VARCHAR(14) para o VO Cpf.
 *
 * Uso no model:
 *   protected function casts(): array
 *   {
 *       return [
 *           'cpf' => CpfCast::class,
 *       ];
 *   }
 *
 * O banco deve armazenar apenas os 11 dígitos (sem formatação).
 *
 * @implements CastsAttributes<?Cpf, Cpf|string|null>
 */
class CpfCast implements CastsAttributes
{
    /**
     * Converte o valor do banco de dados para o VO Cpf.
     * Retorna null se o valor for nulo ou inválido (sem lançar exceção).
     *
     * @return ?Cpf
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Cpf
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Cpf::tryOf((string) $value);
    }

    /**
     * Converte o VO Cpf para string de 11 dígitos antes de persistir no banco.
     *
     * @return ?string
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Cpf) {
            return $value->digits();
        }

        $cpf = Cpf::tryOf((string) $value);

        return $cpf?->digits();
    }
}
