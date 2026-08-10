<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object para percentuais — descontos, impostos, taxas, comissões.
 *
 * Range válido: 0.00000000 até 100.00000000.
 * Toda operação é feita com BCMath para manter precisão fiscal.
 */
final readonly class Percentage
{
    private const PRECISION = 8;

    private function __construct(
        private string $value,
    ) {
        if (! is_numeric($this->value)) {
            throw new InvalidArgumentException("Percentual inválido: [{$this->value}]");
        }

        if (bccomp($this->value, '0', self::PRECISION) < 0) {
            throw new InvalidArgumentException('Percentual não pode ser negativo.');
        }

        if (bccomp($this->value, '100', self::PRECISION) > 0) {
            throw new InvalidArgumentException('Percentual não pode ser maior que 100.');
        }
    }

    /**
     * Cria um Percentage a partir de string ou inteiro (ex: "12.5", 10).
     */
    public static function of(string|int|float $value): static
    {
        return new self(number_format((float) $value, self::PRECISION, '.', ''));
    }

    /**
     * Retorna zero por cento.
     */
    public static function zero(): static
    {
        return new static('0.00000000');
    }

    /**
     * Aplica o percentual sobre um valor Money e retorna o resultado.
     *
     * Exemplo: 12.5% de R$ 100,00 = R$ 12,50
     */
    public function applyTo(Money $money): Money
    {
        $factor = bcdiv($this->value, '100', self::PRECISION);

        return $money->mul($factor);
    }

    /**
     * Retorna o complemento do percentual (100% - this%).
     *
     * Útil para calcular o valor após desconto de forma direta.
     */
    public function complement(): static
    {
        $complement = bcsub('100', $this->value, self::PRECISION);

        return new static($complement);
    }

    /**
     * Verifica igualdade entre dois percentuais.
     */
    public function equals(Percentage $other): bool
    {
        return bccomp($this->value, $other->value, self::PRECISION) === 0;
    }

    /**
     * Verifica se o percentual é zero.
     */
    public function isZero(): bool
    {
        return bccomp($this->value, '0', self::PRECISION) === 0;
    }

    /**
     * Retorna o valor como string para persistência no banco.
     */
    public function toString(): string
    {
        return $this->value;
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
