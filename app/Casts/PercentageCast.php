<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Percentage;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast Eloquent para mapear colunas DECIMAL(8,8) ou DECIMAL(11,8) para o VO Percentage.
 *
 * Uso no model:
 *   protected function casts(): array
 *   {
 *       return [
 *           'desconto'    => PercentageCast::class,
 *           'aliquota_icms' => PercentageCast::class,
 *       ];
 *   }
 *
 * @implements CastsAttributes<?Percentage, Percentage|string|int|float|null>
 */
class PercentageCast implements CastsAttributes
{
    /**
     * Converte o valor do banco de dados para o VO Percentage.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Percentage
    {
        if ($value === null) {
            return null;
        }

        return Percentage::of((string) $value);
    }

    /**
     * Converte o VO Percentage para string antes de persistir no banco.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Percentage) {
            return $value->toString();
        }

        return Percentage::of($value)->toString();
    }
}
