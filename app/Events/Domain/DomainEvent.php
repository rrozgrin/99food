<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Classe base para todos os eventos de domínio do ERP.
 *
 * Domain Events representam algo significativo que ocorreu no sistema
 * (ex: VendaRealizada, CaixaFechado, EstoqueAtualizado).
 *
 * Utiliza o EventDispatcher do Laravel — sem dependência externa.
 * Listeners são registrados via EventServiceProvider.
 *
 * Convenção de nomenclatura: {Domínio}{AçãoNoPassado}Event
 * Ex: VendaRealizadaEvent, ClienteCadastradoEvent, PagamentoRegistradoEvent
 */
abstract class DomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Momento exato em que o evento ocorreu (imutável).
     */
    public readonly DateTimeImmutable $occurredAt;

    /**
     * ID do usuário que disparou a ação (null para processos automáticos).
     */
    public readonly ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->occurredAt = new DateTimeImmutable;
        $this->userId = $userId ?? $this->resolveUserId();
    }

    /**
     * Retorna o nome do evento para rastreabilidade e logs.
     */
    public function eventName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Serializa o evento para log/auditoria.
     * Sobrescrever nos eventos concretos para incluir dados relevantes.
     */
    public function toArray(): array
    {
        return [
            'event' => $this->eventName(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'user_id' => $this->userId,
        ];
    }

    /**
     * Tenta obter o ID do usuário autenticado via guard 'api'.
     */
    private function resolveUserId(): ?int
    {
        try {
            return auth('api')->id();
        } catch (\Throwable) {
            return null;
        }
    }
}
