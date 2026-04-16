<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Money;
use App\ValueObjects\RoundingMode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testes unitários do Value Object Money.
 *
 * Cobre: criação, operações aritméticas, comparações e arredondamentos.
 * Garante que nunca há perda de precisão em cálculos fiscais.
 */
class MoneyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Criação
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_criar_money_a_partir_de_string(): void
    {
        $money = Money::of('100.50000000');
        $this->assertSame('100.50000000', $money->toString());
    }

    #[Test]
    public function deve_criar_money_a_partir_de_inteiro(): void
    {
        $money = Money::of(100);
        $this->assertSame('100.00000000', $money->toString());
    }

    #[Test]
    public function deve_criar_money_zero(): void
    {
        $money = Money::zero();
        $this->assertTrue($money->isZero());
    }

    #[Test]
    public function deve_lancar_excecao_para_valor_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of('abc');
    }

    // -------------------------------------------------------------------------
    // Operações Aritméticas
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_somar_dois_valores(): void
    {
        $a = Money::of('100.50000000');
        $b = Money::of('50.75000000');
        $this->assertSame('151.25000000', $a->add($b)->toString());
    }

    #[Test]
    public function deve_subtrair_dois_valores(): void
    {
        $a = Money::of('100.00000000');
        $b = Money::of('30.50000000');
        $this->assertSame('69.50000000', $a->sub($b)->toString());
    }

    #[Test]
    public function deve_multiplicar_por_fator(): void
    {
        $money = Money::of('50.00000000');
        $result = $money->mul('3');
        $this->assertSame('150.00000000', $result->toString());
    }

    #[Test]
    public function deve_dividir_por_divisor(): void
    {
        $money = Money::of('100.00000000');
        $result = $money->div('4');
        $this->assertSame('25.00000000', $result->toString());
    }

    #[Test]
    public function deve_lancar_excecao_na_divisao_por_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of('100')->div('0');
    }

    #[Test]
    public function deve_retornar_valor_absoluto(): void
    {
        $money = Money::of('-50.00000000');
        $this->assertSame('50.00000000', $money->abs()->toString());
    }

    // -------------------------------------------------------------------------
    // Precisão Fiscal (8 casas decimais)
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_manter_precisao_de_oito_casas_decimais(): void
    {
        $a = Money::of('0.12345678');
        $b = Money::of('0.00000001');
        $this->assertSame('0.12345679', $a->add($b)->toString());
    }

    #[Test]
    public function deve_evitar_erros_de_float_em_soma(): void
    {
        // Em float: 0.1 + 0.2 = 0.30000000000000004
        $a = Money::of('0.10000000');
        $b = Money::of('0.20000000');
        $this->assertSame('0.30000000', $a->add($b)->toString());
    }

    // -------------------------------------------------------------------------
    // Arredondamento
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('halfUpProvider')]
    public function deve_arredondar_com_half_up(string $value, int $scale, string $expected): void
    {
        $result = Money::of($value, roundingMode: RoundingMode::HALF_UP)->round($scale);
        $this->assertSame($expected, $result->toString());
    }

    public static function halfUpProvider(): array
    {
        return [
            'arredonda para cima quando >= 5' => ['1.235', 2, '1.24'],
            'arredonda para baixo quando < 5' => ['1.234', 2, '1.23'],
            'sem arredondamento necessário'   => ['1.230', 2, '1.23'],
            'valor com 8 casas'               => ['10.123456785', 8, '10.12345679'],
        ];
    }

    // -------------------------------------------------------------------------
    // Comparações
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_verificar_igualdade(): void
    {
        $a = Money::of('100.00000000');
        $b = Money::of('100.00000000');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function deve_verificar_maior_que(): void
    {
        $a = Money::of('100.00');
        $b = Money::of('50.00');
        $this->assertTrue($a->greaterThan($b));
        $this->assertFalse($b->greaterThan($a));
    }

    #[Test]
    public function deve_verificar_menor_que(): void
    {
        $a = Money::of('50.00');
        $b = Money::of('100.00');
        $this->assertTrue($a->lessThan($b));
    }

    #[Test]
    public function deve_verificar_positivo_e_negativo(): void
    {
        $this->assertTrue(Money::of('1.00')->isPositive());
        $this->assertTrue(Money::of('-1.00')->isNegative());
        $this->assertFalse(Money::zero()->isPositive());
        $this->assertFalse(Money::zero()->isNegative());
    }
}
