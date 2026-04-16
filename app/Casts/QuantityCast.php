<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Quantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast Eloquent para mapear colunas DECIMAL(15,4) para o VO Quantity.
 *
 * Uso no model:
 *   protected function casts(): array
 *   {
 *       return [
 *           'quantidade'         => QuantityCast::class,
 *           'quantidade_devolvida' => QuantityCast::class,
 *       ];
 *   }
 *
 * @implements CastsAttributes<?Quantity, Quantity|string|int|float|null>
 */
class QuantityCast implements CastsAttributes
{
    public function __construct(
        private readonly int $precision = 4,
    ) {}

    /**
     * Converte o valor do banco de dados para o VO Quantity.
     *
     * @return ?Quantity
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Quantity
    {
        if ($value === null) {
            return null;
        }

        return Quantity::of((string) $value, $this->precision);
    }

    /**
     * Converte o VO Quantity para string antes de persistir no banco.
     *
     * @return ?string
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Quantity) {
            return $value->toString();
        }

        return Quantity::of($value, $this->precision)->toString();
    }
}
