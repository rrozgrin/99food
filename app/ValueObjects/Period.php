<?php

declare(strict_types=1);

namespace App\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Value Object para períodos fiscais e de vigência.
 *
 * Representa um intervalo de datas com data de início e data de fim.
 * Útil para: competência fiscal, vigência de promoções, período de locação,
 * filtros de relatórios financeiros, validade de orçamentos.
 */
final readonly class Period
{
    private function __construct(
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
    ) {
        if ($this->startDate > $this->endDate) {
            throw new InvalidArgumentException(
                'A data inicial não pode ser posterior à data final.'
            );
        }
    }

    /**
     * Cria um Period a partir de dois DateTimeInterface.
     */
    public static function of(DateTimeInterface $startDate, DateTimeInterface $endDate): static
    {
        return new self(
            DateTimeImmutable::createFromInterface($startDate),
            DateTimeImmutable::createFromInterface($endDate),
        );
    }

    /**
     * Cria um Period a partir de strings no formato Y-m-d ou Y-m-d H:i:s.
     *
     * @throws InvalidArgumentException Se o formato da data for inválido.
     */
    public static function fromStrings(string $startDate, string $endDate): static
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $startDate)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDate);

        $end = DateTimeImmutable::createFromFormat('Y-m-d', $endDate)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDate);

        if (! $start || ! $end) {
            throw new InvalidArgumentException(
                "Formato de data inválido. Use Y-m-d ou Y-m-d H:i:s. Recebido: [{$startDate}] e [{$endDate}]"
            );
        }

        return new static($start, $end);
    }

    /**
     * Cria um Period para o mês atual (do primeiro ao último dia).
     */
    public static function currentMonth(): static
    {
        $now = new DateTimeImmutable;

        return new static(
            $now->modify('first day of this month')->setTime(0, 0, 0),
            $now->modify('last day of this month')->setTime(23, 59, 59),
        );
    }

    /**
     * Cria um Period para o ano fiscal atual (1/1 a 31/12).
     */
    public static function currentYear(): static
    {
        $year = (int) (new DateTimeImmutable)->format('Y');

        return new static(
            new DateTimeImmutable("{$year}-01-01 00:00:00"),
            new DateTimeImmutable("{$year}-12-31 23:59:59"),
        );
    }

    // -------------------------------------------------------------------------
    // Consultas
    // -------------------------------------------------------------------------

    /**
     * Verifica se uma data está dentro do período (inclusivo).
     */
    public function contains(DateTimeInterface $date): bool
    {
        $dt = DateTimeImmutable::createFromInterface($date);

        return $dt >= $this->startDate && $dt <= $this->endDate;
    }

    /**
     * Verifica se dois períodos se sobrepõem.
     */
    public function overlaps(Period $other): bool
    {
        return $this->startDate <= $other->endDate && $this->endDate >= $other->startDate;
    }

    /**
     * Retorna o número de dias do período (inclusivo).
     */
    public function days(): int
    {
        return (int) $this->startDate->diff($this->endDate)->days + 1;
    }

    /**
     * Verifica igualdade entre dois períodos.
     */
    public function equals(Period $other): bool
    {
        return $this->startDate == $other->startDate && $this->endDate == $other->endDate;
    }

    // -------------------------------------------------------------------------
    // Acessores
    // -------------------------------------------------------------------------

    /**
     * Retorna a data de início.
     */
    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Retorna a data de fim.
     */
    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * Retorna a data de início formatada como string.
     */
    public function startDateToString(string $format = 'Y-m-d'): string
    {
        return $this->startDate->format($format);
    }

    /**
     * Retorna a data de fim formatada como string.
     */
    public function endDateToString(string $format = 'Y-m-d'): string
    {
        return $this->endDate->format($format);
    }

    public function __toString(): string
    {
        return "{$this->startDateToString()} / {$this->endDateToString()}";
    }
}
