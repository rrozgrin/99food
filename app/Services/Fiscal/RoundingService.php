<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\ValueObjects\RoundingMode;

/**
 * Serviço de arredondamento para cálculos fiscais e monetários.
 *
 * Implementa os modos de arredondamento suportados pelo sistema.
 * O padrão HALF_UP segue a ABNT NBR 5891 (arredondamento fiscal brasileiro).
 *
 * Todas as operações são feitas com BCMath (precisão arbitrária — nunca float).
 */
class RoundingService
{
    private const INTERNAL_PRECISION = 10;

    /**
     * Arredonda um valor string com BCMath usando o modo informado.
     *
     * @param  string      $value     Valor a arredondar (string BCMath).
     * @param  int         $scale     Número de casas decimais do resultado.
     * @param  RoundingMode $mode     Modo de arredondamento.
     */
    public function round(string $value, int $scale, RoundingMode $mode = RoundingMode::HALF_UP): string
    {
        return match ($mode) {
            RoundingMode::HALF_UP   => $this->halfUp($value, $scale),
            RoundingMode::HALF_DOWN => $this->halfDown($value, $scale),
            RoundingMode::HALF_EVEN => $this->halfEven($value, $scale),
            RoundingMode::TRUNCATE  => bcadd($value, '0', $scale),
        };
    }

    /**
     * HALF_UP — Arredonda para cima quando o dígito é >= 5.
     * Padrão ABNT NBR 5891 — obrigatório para cálculos fiscais no Brasil.
     *
     * Exemplos: 1.235 → 1.24 | 1.234 → 1.23 | -1.235 → -1.24
     */
    public function halfUp(string $value, int $scale): string
    {
        $isNegative = bccomp($value, '0', self::INTERNAL_PRECISION) < 0;
        $abs = $isNegative ? bcmul($value, '-1', self::INTERNAL_PRECISION) : $value;

        $shift = bcpow('10', (string) $scale, 0);
        $shifted = bcmul($abs, $shift, self::INTERNAL_PRECISION);
        $rounded = bcadd($shifted, '0.5', 0);
        $result = bcdiv($rounded, $shift, $scale);

        return $isNegative ? bcmul($result, '-1', $scale) : $result;
    }

    /**
     * HALF_DOWN — Arredonda para baixo quando o dígito é >= 5.
     *
     * Exemplos: 1.235 → 1.23 | 1.236 → 1.24
     */
    public function halfDown(string $value, int $scale): string
    {
        $isNegative = bccomp($value, '0', self::INTERNAL_PRECISION) < 0;
        $abs = $isNegative ? bcmul($value, '-1', self::INTERNAL_PRECISION) : $value;

        $shift = bcpow('10', (string) $scale, 0);
        $shifted = bcmul($abs, $shift, self::INTERNAL_PRECISION);
        // Subtrai uma fração ínfima antes de truncar para implementar HALF_DOWN
        $adjusted = bcadd($shifted, '0.4999999999', 0);
        $result = bcdiv($adjusted, $shift, $scale);

        return $isNegative ? bcmul($result, '-1', $scale) : $result;
    }

    /**
     * HALF_EVEN (Banker's Rounding) — Arredonda para o número par mais próximo.
     * Minimiza viés acumulado em grandes volumes de cálculos.
     *
     * Exemplos: 1.235 → 1.24 | 1.245 → 1.24 | 1.255 → 1.26
     */
    public function halfEven(string $value, int $scale): string
    {
        $shift = bcpow('10', (string) $scale, 0);
        $shifted = bcmul($value, $shift, self::INTERNAL_PRECISION);
        $floor = bcadd($shifted, '0', 0);
        $remainder = bcsub($shifted, $floor, self::INTERNAL_PRECISION);
        $cmp = bccomp($remainder, '0.5', self::INTERNAL_PRECISION);

        if ($cmp < 0) {
            return bcdiv($floor, $shift, $scale);
        }

        if ($cmp > 0) {
            return bcdiv(bcadd($floor, '1', 0), $shift, $scale);
        }

        // Exatamente 0.5 — arredonda para o par
        $floorInt = (int) $floor;
        $rounded = ($floorInt % 2 === 0) ? $floorInt : $floorInt + 1;

        return bcdiv((string) $rounded, $shift, $scale);
    }

    /**
     * TRUNCATE — Remove casas decimais sem arredondar (floor em direção ao zero).
     *
     * Exemplos: 1.239 → 1.23 | 1.231 → 1.23
     */
    public function truncate(string $value, int $scale): string
    {
        return bcadd($value, '0', $scale);
    }
}
