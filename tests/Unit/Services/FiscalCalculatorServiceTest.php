<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Fiscal\FiscalCalculatorService;
use App\Services\Fiscal\RoundingService;
use App\ValueObjects\Money;
use App\ValueObjects\Percentage;
use App\ValueObjects\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testes unitários do FiscalCalculatorService.
 *
 * Valida operações fiscais críticas: desconto, acréscimo, impostos e rateio.
 */
class FiscalCalculatorServiceTest extends TestCase
{
    private FiscalCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new FiscalCalculatorService(new RoundingService);
    }

    // -------------------------------------------------------------------------
    // Desconto
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_aplicar_desconto_percentual(): void
    {
        $price = Money::of('100.00000000');
        $discount = Percentage::of('10');

        $result = $this->calculator->applyDiscount($price, $discount);

        $this->assertSame('90.00000000', $result->toString());
    }

    #[Test]
    public function deve_calcular_valor_do_desconto(): void
    {
        $price = Money::of('200.00000000');
        $discount = Percentage::of('15');

        $amount = $this->calculator->discountAmount($price, $discount);

        $this->assertSame('30.00000000', $amount->toString());
    }

    #[Test]
    public function deve_lancar_excecao_se_desconto_fixo_maior_que_preco(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->applyFixedDiscount(
            price: Money::of('50.00'),
            discountAmount: Money::of('60.00'),
        );
    }

    // -------------------------------------------------------------------------
    // Acréscimo
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_aplicar_acrescimo_percentual(): void
    {
        $price = Money::of('100.00000000');
        $surcharge = Percentage::of('5');

        $result = $this->calculator->applySurcharge($price, $surcharge);

        $this->assertSame('105.00000000', $result->toString());
    }

    // -------------------------------------------------------------------------
    // Total de Itens
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_calcular_total_quantidade_vezes_preco(): void
    {
        $qty = Quantity::of('3.0000');
        $price = Money::of('25.50000000');

        $total = $this->calculator->calculateTotal($qty, $price);

        $this->assertSame('76.50000000', $total->toString());
    }

    #[Test]
    public function deve_somar_lista_de_valores(): void
    {
        $items = [
            Money::of('10.00000000'),
            Money::of('20.00000000'),
            Money::of('30.00000000'),
        ];

        $total = $this->calculator->sum($items);

        $this->assertSame('60.00000000', $total->toString());
    }

    // -------------------------------------------------------------------------
    // Impostos
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_calcular_imposto_icms_12_por_cento(): void
    {
        $base = Money::of('1000.00000000');
        $rate = Percentage::of('12');

        $tax = $this->calculator->calculateTax($base, $rate, 'ICMS');

        $this->assertSame('120.00000000', $tax->toString());
    }

    // -------------------------------------------------------------------------
    // Rateio Proporcional
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_ratear_valor_proporcionalmente(): void
    {
        $total = Money::of('100.00000000');
        $weights = [
            Money::of('30.00000000'),
            Money::of('70.00000000'),
        ];

        $distributed = $this->calculator->proRata($total, $weights);

        $this->assertSame('30.00000000', $distributed[0]->toString());
        $this->assertSame('70.00000000', $distributed[1]->toString());
    }

    #[Test]
    public function soma_do_rateio_deve_ser_exatamente_igual_ao_total(): void
    {
        $total = Money::of('100.00000000');
        $weights = [
            Money::of('33.33000000'),
            Money::of('33.33000000'),
            Money::of('33.34000000'),
        ];

        $distributed = $this->calculator->proRata($total, $weights);

        $sum = $this->calculator->sum($distributed);
        $this->assertTrue($total->equals($sum), 'Soma do rateio deve ser exatamente igual ao total');
    }

    #[Test]
    public function deve_lancar_excecao_se_soma_dos_pesos_for_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->proRata(
            total: Money::of('100.00'),
            weights: [Money::zero(), Money::zero()],
        );
    }

    // -------------------------------------------------------------------------
    // Tolerância
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_aceitar_diferenca_dentro_da_tolerancia(): void
    {
        $calculated = Money::of('100.00000000');
        $informed = Money::of('100.00500000');

        $this->assertTrue($this->calculator->withinTolerance($calculated, $informed, '0.01'));
    }

    #[Test]
    public function deve_rejeitar_diferenca_fora_da_tolerancia(): void
    {
        $calculated = Money::of('100.00000000');
        $informed = Money::of('100.02000000');

        $this->assertFalse($this->calculator->withinTolerance($calculated, $informed, '0.01'));
    }
}
