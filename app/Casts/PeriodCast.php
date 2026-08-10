<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Period;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast Eloquent para mapear duas colunas de data (início e fim) para o VO Period.
 *
 * Como Period requer duas colunas, este cast deve ser usado com atributo computado.
 *
 * Uso recomendado no model via método accessor:
 *
 *   public function getPeriodo(): ?Period
 *   {
 *       if (!$this->data_inicio || !$this->data_fim) return null;
 *       return Period::fromStrings((string) $this->data_inicio, (string) $this->data_fim);
 *   }
 *
 *   public function setPeriodo(Period $period): void
 *   {
 *       $this->data_inicio = $period->startDateToString();
 *       $this->data_fim    = $period->endDateToString();
 *   }
 *
 * Para uso direto como cast de coluna única (ex: JSON ou período serializado):
 *   Coluna: VARCHAR - formato "Y-m-d/Y-m-d"
 *
 *   protected function casts(): array
 *   {
 *       return ['periodo_vigencia' => PeriodCast::class];
 *   }
 *
 * @implements CastsAttributes<?Period, Period|null>
 */
class PeriodCast implements CastsAttributes
{
    /**
     * Converte "Y-m-d/Y-m-d" do banco para o VO Period.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Period
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode('/', (string) $value, 2);

        if (count($parts) !== 2) {
            return null;
        }

        try {
            return Period::fromStrings(trim($parts[0]), trim($parts[1]));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Converte o VO Period para "Y-m-d/Y-m-d" antes de persistir no banco.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Period) {
            return $value->startDateToString().'/'.$value->endDateToString();
        }

        return null;
    }
}
