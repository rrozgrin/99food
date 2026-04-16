<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\ValueObjects\Money;
use App\ValueObjects\Percentage;
use App\ValueObjects\Quantity;
use App\ValueObjects\RoundingMode;
use InvalidArgumentException;

/**
 * Serviço de cálculo fiscal para o ERP.
 *
 * Centraliza todos os cálculos fiscais e monetários do sistema:
 * desconto, acréscimo, impostos (ICMS, ISS, PIS, COFINS), totais,
 * rateio proporcional e validações de regras fiscais.
 *
 * Todas as operações usam BCMath via VO Money — nunca float.
 * O arredondamento padrão segue a ABNT NBR 5891 (HALF_UP).
 */
class FiscalCalculatorService
{
    /** Precisão padrão para valores fiscais: 8 casas decimais. */
    private const FISCAL_PRECISION = 8;

    /** Precisão para exibição ao usuário: 2 casas decimais. */
    private const DISPLAY_PRECISION = 2;

    public function __construct(
        private readonly RoundingService $roundingService,
    ) {}

    // -------------------------------------------------------------------------
    // Desconto e Acréscimo
    // -------------------------------------------------------------------------

    /**
     * Aplica um percentual de desconto sobre um valor.
     *
     * Exemplo: preço R$ 100,00 com 10% de desconto = R$ 90,00
     */
    public function applyDiscount(Money $price, Percentage $discount): Money
    {
        $discountAmount = $discount->applyTo($price);

        return $price->sub($discountAmount);
    }

    /**
     * Calcula o valor do desconto (sem aplicar ao preço).
     */
    public function discountAmount(Money $price, Percentage $discount): Money
    {
        return $discount->applyTo($price);
    }

    /**
     * Aplica um percentual de acréscimo sobre um valor.
     *
     * Exemplo: preço R$ 100,00 com 5% de acréscimo = R$ 105,00
     */
    public function applySurcharge(Money $price, Percentage $surcharge): Money
    {
        $surchargeAmount = $surcharge->applyTo($price);

        return $price->add($surchargeAmount);
    }

    /**
     * Aplica desconto em valor fixo (não percentual).
     *
     * @throws InvalidArgumentException Se o desconto for maior que o preço.
     */
    public function applyFixedDiscount(Money $price, Money $discountAmount): Money
    {
        if ($discountAmount->greaterThan($price)) {
            throw new InvalidArgumentException(
                'O desconto não pode ser maior que o valor do item.'
            );
        }

        return $price->sub($discountAmount);
    }

    // -------------------------------------------------------------------------
    // Total de Itens
    // -------------------------------------------------------------------------

    /**
     * Calcula o total de um item: quantidade × valor unitário.
     */
    public function calculateTotal(Quantity $quantity, Money $unitPrice): Money
    {
        return $quantity->times($unitPrice);
    }

    /**
     * Calcula o total de um item com desconto aplicado.
     */
    public function calculateTotalWithDiscount(
        Quantity $quantity,
        Money $unitPrice,
        Percentage $discount,
    ): Money {
        $priceWithDiscount = $this->applyDiscount($unitPrice, $discount);

        return $quantity->times($priceWithDiscount);
    }

    /**
     * Soma uma lista de valores Money com precisão total (sem perda por arredondamento intermediário).
     *
     * @param  Money[] $items
     */
    public function sum(array $items): Money
    {
        $total = Money::zero(self::FISCAL_PRECISION);

        foreach ($items as $item) {
            $total = $total->add($item);
        }

        return $total;
    }

    // -------------------------------------------------------------------------
    // Impostos
    // -------------------------------------------------------------------------

    /**
     * Calcula o valor de um imposto sobre a base de cálculo.
     *
     * Impostos suportados: ICMS, ISS, PIS, COFINS, IPI, etc.
     * O tipo é informativo — a lógica de cálculo é idêntica (base × alíquota).
     *
     * @param  string $taxType Tipo do imposto (apenas para rastreabilidade/log).
     */
    public function calculateTax(Money $baseValue, Percentage $rate, string $taxType = ''): Money
    {
        return $rate->applyTo($baseValue);
    }

    /**
     * Calcula ICMS pelo método "por dentro" (base de cálculo inclui o próprio imposto).
     *
     * Fórmula: Base = Valor / (1 - Alíquota/100)
     * ICMS = Base × Alíquota / 100
     */
    public function calculateIcmsInternal(Money $value, Percentage $rate): array
    {
        $aliquota = bcdiv($rate->toString(), '100', self::FISCAL_PRECISION);
        $divisor = bcsub('1', $aliquota, self::FISCAL_PRECISION);

        if (bccomp($divisor, '0', self::FISCAL_PRECISION) <= 0) {
            throw new InvalidArgumentException('Alíquota de ICMS inválida (>=100%).');
        }

        $base = Money::of(bcdiv($value->toString(), $divisor, self::FISCAL_PRECISION));
        $tax = $rate->applyTo($base);

        return [
            'base'  => $base,
            'icms'  => $tax,
        ];
    }

    // -------------------------------------------------------------------------
    // Rateio Proporcional
    // -------------------------------------------------------------------------

    /**
     * Distribui um valor total proporcionalmente entre itens com pesos distintos.
     *
     * Garante que a soma dos rateios seja exatamente igual ao total,
     * atribuindo a diferença de arredondamento ao último item.
     *
     * @param  Money    $total   Valor a ser distribuído.
     * @param  Money[]  $weights Pesos proporcionais (ex: subtotais dos itens).
     * @return Money[]           Array com o mesmo índice dos pesos.
     *
     * @throws InvalidArgumentException Se a soma dos pesos for zero.
     */
    public function proRata(Money $total, array $weights): array
    {
        $sumWeights = $this->sum($weights);

        if ($sumWeights->isZero()) {
            throw new InvalidArgumentException('A soma dos pesos para rateio não pode ser zero.');
        }

        $distributed = [];
        $sumDistributed = Money::zero(self::FISCAL_PRECISION);

        $lastIndex = array_key_last($weights);

        foreach ($weights as $index => $weight) {
            if ($index === $lastIndex) {
                // Último item recebe a diferença para garantir que a soma seja exata
                $distributed[$index] = $total->sub($sumDistributed);
                break;
            }

            $ratio = bcdiv($weight->toString(), $sumWeights->toString(), self::FISCAL_PRECISION);
            $share = $total->mul($ratio)->round(self::FISCAL_PRECISION);

            $distributed[$index] = $share;
            $sumDistributed = $sumDistributed->add($share);
        }

        return $distributed;
    }

    // -------------------------------------------------------------------------
    // Utilitários
    // -------------------------------------------------------------------------

    /**
     * Arredonda um valor Money para exibição ao usuário (2 casas decimais).
     */
    public function toDisplay(Money $money): Money
    {
        return $money->round(self::DISPLAY_PRECISION);
    }

    /**
     * Arredonda um valor Money para precisão fiscal completa (8 casas decimais).
     */
    public function toFiscal(Money $money): Money
    {
        return $money->round(self::FISCAL_PRECISION);
    }

    /**
     * Verifica se um valor está dentro de uma faixa de tolerância aceitável.
     * Útil para comparar totais calculados pelo sistema vs. informados pelo usuário.
     *
     * @param  Money  $calculated   Valor calculado pelo sistema.
     * @param  Money  $informed     Valor informado pelo usuário/parceiro.
     * @param  string $tolerance    Tolerância máxima em valor absoluto (ex: '0.01').
     */
    public function withinTolerance(Money $calculated, Money $informed, string $tolerance = '0.01'): bool
    {
        $difference = $calculated->sub($informed)->abs();
        $toleranceMoney = Money::of($tolerance, self::FISCAL_PRECISION);

        return $difference->lessThanOrEqual($toleranceMoney);
    }
}
