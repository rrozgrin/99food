<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Modos de arredondamento disponíveis para cálculos fiscais.
 *
 * O modo HALF_UP é o padrão conforme ABNT NBR 5891 (arredondamento fiscal brasileiro).
 */
enum RoundingMode
{
    /** Arredonda para cima quando o dígito é >= 5 (padrão ABNT NBR 5891). */
    case HALF_UP;

    /** Arredonda para baixo quando o dígito é >= 5. */
    case HALF_DOWN;

    /** Arredonda para o par mais próximo (banker's rounding). */
    case HALF_EVEN;

    /** Trunca sem arredondar. */
    case TRUNCATE;
}
