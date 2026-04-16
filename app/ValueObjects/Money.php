<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object para valores monetários e fiscais.
 *
 * Utiliza BCMath para aritmética de precisão exata — nunca float.
 * Suporta até 8 casas decimais (exigência fiscal e de arredondamento).
 *
 * Colunas correspondentes no banco de dados devem ser DECIMAL(15,8).
 */
final readonly class Money
{
    /** Precisão padrão: 8 casas decimais. */
    private const DEFAULT_PRECISION = 8;

    private function __construct(
        private string $amount,
        private int $precision = self::DEFAULT_PRECISION,
        private RoundingMode $roundingMode = RoundingMode::HALF_UP,
    ) {
        if (! is_numeric($this->amount)) {
            throw new InvalidArgumentException("Valor monetário inválido: [{$this->amount}]");
        }
    }

    /**
     * Cria um Money a partir de string ou inteiro.
     * Este é o método principal de criação — evita float.
     */
    public static function of(
        string|int $amount,
        int $precision = self::DEFAULT_PRECISION,
        RoundingMode $roundingMode = RoundingMode::HALF_UP,
    ): static {
        return new static((string) $amount, $precision, $roundingMode);
    }

    /**
     * Cria um Money a partir de float — use apenas para leitura de dados externos.
     * Converte float para string com a precisão informada antes de armazenar.
     */
    public static function fromFloat(
        float $amount,
        int $precision = self::DEFAULT_PRECISION,
        RoundingMode $roundingMode = RoundingMode::HALF_UP,
    ): static {
        return new static(number_format($amount, $precision, '.', ''), $precision, $roundingMode);
    }

    /**
     * Retorna zero com a precisão padrão.
     */
    public static function zero(int $precision = self::DEFAULT_PRECISION): static
    {
        return new static('0', $precision);
    }

    // -------------------------------------------------------------------------
    // Operações aritméticas (resultado sempre com a mesma precisão)
    // -------------------------------------------------------------------------

    /**
     * Soma dois valores monetários.
     */
    public function add(Money $other): static
    {
        $result = bcadd($this->amount, $other->amount, $this->precision);

        return new static($result, $this->precision, $this->roundingMode);
    }

    /**
     * Subtrai um valor monetário.
     */
    public function sub(Money $other): static
    {
        $result = bcsub($this->amount, $other->amount, $this->precision);

        return new static($result, $this->precision, $this->roundingMode);
    }

    /**
     * Multiplica por um fator escalar (string para evitar perda de precisão).
     */
    public function mul(string|int $multiplier): static
    {
        $result = bcmul($this->amount, (string) $multiplier, $this->precision);

        return new static($result, $this->precision, $this->roundingMode);
    }

    /**
     * Divide pelo divisor informado.
     *
     * @throws InvalidArgumentException Quando o divisor é zero.
     */
    public function div(string|int $divisor): static
    {
        if (bccomp((string) $divisor, '0', $this->precision) === 0) {
            throw new InvalidArgumentException('Divisão por zero não é permitida.');
        }

        $result = bcdiv($this->amount, (string) $divisor, $this->precision);

        return new static($result, $this->precision, $this->roundingMode);
    }

    /**
     * Valor absoluto (remove sinal negativo).
     */
    public function abs(): static
    {
        $result = bccomp($this->amount, '0', $this->precision) < 0
            ? bcmul($this->amount, '-1', $this->precision)
            : $this->amount;

        return new static($result, $this->precision, $this->roundingMode);
    }

    /**
     * Arredonda para o número de casas decimais informado,
     * usando a estratégia de arredondamento configurada.
     */
    public function round(int $scale): static
    {
        $rounded = match ($this->roundingMode) {
            RoundingMode::HALF_UP   => $this->roundHalfUp($this->amount, $scale),
            RoundingMode::HALF_DOWN => $this->roundHalfDown($this->amount, $scale),
            RoundingMode::HALF_EVEN => $this->roundHalfEven($this->amount, $scale),
            RoundingMode::TRUNCATE  => bcadd($this->amount, '0', $scale),
        };

        return new static($rounded, $scale, $this->roundingMode);
    }

    // -------------------------------------------------------------------------
    // Comparações
    // -------------------------------------------------------------------------

    /**
     * Verifica igualdade entre dois valores.
     */
    public function equals(Money $other): bool
    {
        return bccomp($this->amount, $other->amount, $this->precision) === 0;
    }

    /**
     * Verifica se este valor é maior que o informado.
     */
    public function greaterThan(Money $other): bool
    {
        return bccomp($this->amount, $other->amount, $this->precision) > 0;
    }

    /**
     * Verifica se este valor é menor que o informado.
     */
    public function lessThan(Money $other): bool
    {
        return bccomp($this->amount, $other->amount, $this->precision) < 0;
    }

    /**
     * Verifica se este valor é maior ou igual ao informado.
     */
    public function greaterThanOrEqual(Money $other): bool
    {
        return bccomp($this->amount, $other->amount, $this->precision) >= 0;
    }

    /**
     * Verifica se este valor é menor ou igual ao informado.
     */
    public function lessThanOrEqual(Money $other): bool
    {
        return bccomp($this->amount, $other->amount, $this->precision) <= 0;
    }

    /**
     * Verifica se o valor é zero.
     */
    public function isZero(): bool
    {
        return bccomp($this->amount, '0', $this->precision) === 0;
    }

    /**
     * Verifica se o valor é positivo (> 0).
     */
    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', $this->precision) > 0;
    }

    /**
     * Verifica se o valor é negativo (< 0).
     */
    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', $this->precision) < 0;
    }

    // -------------------------------------------------------------------------
    // Saída / Conversão
    // -------------------------------------------------------------------------

    /**
     * Retorna a representação string para persistência no banco de dados.
     * Sempre com o número exato de casas decimais configurado.
     */
    public function toString(): string
    {
        return number_format((float) $this->amount, $this->precision, '.', '');
    }

    /**
     * Retorna float — use APENAS para exibição ou integração com APIs externas.
     * Nunca use float em cálculos intermediários.
     */
    public function toFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * Retorna a precisão configurada.
     */
    public function getPrecision(): int
    {
        return $this->precision;
    }

    /**
     * Retorna o modo de arredondamento configurado.
     */
    public function getRoundingMode(): RoundingMode
    {
        return $this->roundingMode;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    // -------------------------------------------------------------------------
    // Arredondamento interno (BCMath puro)
    // -------------------------------------------------------------------------

    private function roundHalfUp(string $value, int $scale): string
    {
        $shift = bcpow('10', (string) $scale, 0);
        $isNegative = bccomp($value, '0', $this->precision) < 0;
        $abs = $isNegative ? bcmul($value, '-1', $this->precision) : $value;
        $shifted = bcmul($abs, $shift, $this->precision);
        $floor = bcadd($shifted, '0.5', 0); // trunca (bcadd escala 0 = floor)
        $result = bcdiv($floor, $shift, $scale);

        return $isNegative ? bcmul($result, '-1', $scale) : $result;
    }

    private function roundHalfDown(string $value, int $scale): string
    {
        $shift = bcpow('10', (string) $scale, 0);
        $isNegative = bccomp($value, '0', $this->precision) < 0;
        $abs = $isNegative ? bcmul($value, '-1', $this->precision) : $value;
        $shifted = bcmul($abs, $shift, $this->precision);
        // HALF_DOWN: adiciona 0.4999... em vez de 0.5
        $adjusted = bcadd($shifted, '0.4999999999', 0);
        $result = bcdiv($adjusted, $shift, $scale);

        return $isNegative ? bcmul($result, '-1', $scale) : $result;
    }

    private function roundHalfEven(string $value, int $scale): string
    {
        // Banker's rounding: usa HALF_UP ou HALF_DOWN dependendo da paridade
        $shift = bcpow('10', (string) $scale, 0);
        $shifted = bcmul($value, $shift, $this->precision);
        $floor = bcadd($shifted, '0', 0);
        $remainder = bcsub($shifted, $floor, $this->precision);
        $half = bccomp($remainder, '0.5', $this->precision);

        if ($half < 0) {
            return bcdiv($floor, $shift, $scale);
        }

        if ($half > 0) {
            return bcdiv(bcadd($floor, '1', 0), $shift, $scale);
        }

        // Exatamente 0.5 — arredonda para o par
        $floorInt = (int) $floor;
        $rounded = ($floorInt % 2 === 0) ? $floorInt : $floorInt + 1;

        return bcdiv((string) $rounded, $shift, $scale);
    }
}
