<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object para CPF (Cadastro de Pessoas Físicas).
 *
 * Realiza validação completa dos dígitos verificadores.
 * Armazena internamente apenas os 11 dígitos numéricos.
 */
final readonly class Cpf
{
    private string $digits;

    private function __construct(string $cpf)
    {
        $this->digits = preg_replace('/\D/', '', $cpf);

        if (! $this->validate()) {
            throw new InvalidArgumentException("CPF inválido: [{$cpf}]");
        }
    }

    /**
     * Cria um CPF a partir de string (com ou sem formatação).
     *
     * @throws InvalidArgumentException Se o CPF for inválido.
     */
    public static function of(string $cpf): static
    {
        return new static($cpf);
    }

    /**
     * Tenta criar um CPF sem lançar exceção. Retorna null se inválido.
     */
    public static function tryOf(string $cpf): ?static
    {
        try {
            return static::of($cpf);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Verifica se uma string é um CPF válido (sem instanciar).
     */
    public static function isValid(string $cpf): bool
    {
        return static::tryOf($cpf) !== null;
    }

    /**
     * Retorna o CPF formatado: 000.000.000-00
     */
    public function formatted(): string
    {
        return substr($this->digits, 0, 3) . '.'
            . substr($this->digits, 3, 3) . '.'
            . substr($this->digits, 6, 3) . '-'
            . substr($this->digits, 9, 2);
    }

    /**
     * Retorna apenas os 11 dígitos numéricos (para banco de dados).
     */
    public function digits(): string
    {
        return $this->digits;
    }

    /**
     * Verifica igualdade entre dois CPFs.
     */
    public function equals(Cpf $other): bool
    {
        return $this->digits === $other->digits;
    }

    public function __toString(): string
    {
        return $this->formatted();
    }

    // -------------------------------------------------------------------------
    // Validação interna
    // -------------------------------------------------------------------------

    private function validate(): bool
    {
        if (strlen($this->digits) !== 11) {
            return false;
        }

        // Rejeita sequências repetidas (ex: 000.000.000-00)
        if (preg_match('/^(\d)\1{10}$/', $this->digits)) {
            return false;
        }

        return $this->validateDigit(9) && $this->validateDigit(10);
    }

    private function validateDigit(int $position): bool
    {
        $sum = 0;
        $weight = $position + 1;

        for ($i = 0; $i < $position; $i++) {
            $sum += (int) $this->digits[$i] * $weight--;
        }

        $remainder = $sum % 11;
        $expected = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $this->digits[$position] === $expected;
    }
}
