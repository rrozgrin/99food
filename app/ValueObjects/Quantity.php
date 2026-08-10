<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object para quantidades de produtos, serviços e equipamentos.
 *
 * Suporta decimais (ex: 2.500 kg, 0.75 horas, 1.333 litros).
 * Precisão padrão de 4 casas decimais (suficiente para grandezas físicas).
 * Toda operação é feita com BCMath.
 */
final readonly class Quantity
{
    private const DEFAULT_PRECISION = 4;

    private function __construct(
        private string $value,
        private int $precision = self::DEFAULT_PRECISION,
    ) {
        if (! is_numeric($this->value)) {
            throw new InvalidArgumentException("Quantidade inválida: [{$this->value}]");
        }

        if (bccomp($this->value, '0', $this->precision) < 0) {
            throw new InvalidArgumentException('Quantidade não pode ser negativa.');
        }
    }

    /**
     * Cria uma Quantity a partir de string, inteiro ou float.
     */
    public static function of(string|int|float $value, int $precision = self::DEFAULT_PRECISION): static
    {
        return new self(number_format((float) $value, $precision, '.', ''), $precision);
    }

    /**
     * Retorna zero.
     */
    public static function zero(int $precision = self::DEFAULT_PRECISION): static
    {
        return new static('0', $precision);
    }

    /**
     * Multiplica a quantidade pelo valor unitário e retorna o total em Money.
     *
     * Exemplo: 2.500 kg × R$ 5,00/kg = R$ 12,50
     */
    public function times(Money $unitPrice): Money
    {
        return $unitPrice->mul($this->value);
    }

    /**
     * Soma duas quantidades.
     */
    public function add(Quantity $other): static
    {
        $result = bcadd($this->value, $other->value, $this->precision);

        return new static($result, $this->precision);
    }

    /**
     * Subtrai uma quantidade (resultado mínimo é zero).
     *
     * @throws InvalidArgumentException Se o resultado for negativo.
     */
    public function sub(Quantity $other): static
    {
        $result = bcsub($this->value, $other->value, $this->precision);

        if (bccomp($result, '0', $this->precision) < 0) {
            throw new InvalidArgumentException('Quantidade resultante não pode ser negativa.');
        }

        return new static($result, $this->precision);
    }

    /**
     * Verifica igualdade entre duas quantidades.
     */
    public function equals(Quantity $other): bool
    {
        return bccomp($this->value, $other->value, $this->precision) === 0;
    }

    /**
     * Verifica se a quantidade é zero.
     */
    public function isZero(): bool
    {
        return bccomp($this->value, '0', $this->precision) === 0;
    }

    /**
     * Verifica se a quantidade é maior que a informada.
     */
    public function greaterThan(Quantity $other): bool
    {
        return bccomp($this->value, $other->value, $this->precision) > 0;
    }

    /**
     * Retorna a precisão configurada.
     */
    public function getPrecision(): int
    {
        return $this->precision;
    }

    /**
     * Retorna o valor como string para persistência no banco.
     */
    public function toString(): string
    {
        return number_format((float) $this->value, $this->precision, '.', '');
    }

    /**
     * Retorna o valor como float — somente para exibição.
     */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
