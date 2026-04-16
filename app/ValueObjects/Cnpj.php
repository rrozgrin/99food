<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object para CNPJ (Cadastro Nacional da Pessoa Jurídica).
 *
 * Realiza validação completa dos dígitos verificadores.
 * Armazena internamente apenas os 14 dígitos numéricos.
 */
final readonly class Cnpj
{
    private string $digits;

    private function __construct(string $cnpj)
    {
        $this->digits = preg_replace('/\D/', '', $cnpj);

        if (! $this->validate()) {
            throw new InvalidArgumentException("CNPJ inválido: [{$cnpj}]");
        }
    }

    /**
     * Cria um CNPJ a partir de string (com ou sem formatação).
     *
     * @throws InvalidArgumentException Se o CNPJ for inválido.
     */
    public static function of(string $cnpj): static
    {
        return new static($cnpj);
    }

    /**
     * Tenta criar um CNPJ sem lançar exceção. Retorna null se inválido.
     */
    public static function tryOf(string $cnpj): ?static
    {
        try {
            return static::of($cnpj);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Verifica se uma string é um CNPJ válido (sem instanciar).
     */
    public static function isValid(string $cnpj): bool
    {
        return static::tryOf($cnpj) !== null;
    }

    /**
     * Retorna o CNPJ formatado: 00.000.000/0000-00
     */
    public function formatted(): string
    {
        return substr($this->digits, 0, 2) . '.'
            . substr($this->digits, 2, 3) . '.'
            . substr($this->digits, 5, 3) . '/'
            . substr($this->digits, 8, 4) . '-'
            . substr($this->digits, 12, 2);
    }

    /**
     * Retorna apenas os 14 dígitos numéricos (para banco de dados).
     */
    public function digits(): string
    {
        return $this->digits;
    }

    /**
     * Verifica igualdade entre dois CNPJs.
     */
    public function equals(Cnpj $other): bool
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
        if (strlen($this->digits) !== 14) {
            return false;
        }

        // Rejeita sequências repetidas (ex: 00.000.000/0000-00)
        if (preg_match('/^(\d)\1{13}$/', $this->digits)) {
            return false;
        }

        return $this->validateDigit(12) && $this->validateDigit(13);
    }

    private function validateDigit(int $position): bool
    {
        $weights = $position === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (int) $this->digits[$i] * $weight;
        }

        $remainder = $sum % 11;
        $expected = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $this->digits[$position] === $expected;
    }
}
