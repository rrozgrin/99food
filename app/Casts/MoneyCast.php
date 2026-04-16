<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast Eloquent para mapear colunas DECIMAL(15,8) para o Value Object Money.
 *
 * Uso no model:
 *   protected function casts(): array
 *   {
 *       return [
 *           'valor_unitario' => MoneyCast::class,
 *           'valor_total'    => MoneyCast::class,
 *       ];
 *   }
 *
 * O banco deve usar DECIMAL(15,8) para garantir a precisão de 8 casas decimais.
 *
 * @implements CastsAttributes<?Money, Money|string|int|null>
 */
class MoneyCast implements CastsAttributes
{
    /**
     * Converte o valor do banco de dados para o VO Money.
     *
     * @return ?Money
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::of((string) $value);
    }

    /**
     * Converte o VO Money para string antes de persistir no banco.
     *
     * @return ?string
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->toString();
        }

        // Aceita string/int/float diretamente para flexibilidade de atribuição
        return Money::of((string) $value)->toString();
    }
}
