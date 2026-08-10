<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Trait de auditoria para Models Eloquent.
 *
 * Registra automaticamente todas as operações de criação, alteração e exclusão
 * com: usuário logado, IP de origem, payload anterior e posterior.
 *
 * Uso no Model:
 *   class Produto extends Model
 *   {
 *       use Auditable;
 *       protected array $auditExclude = ['senha', 'api_key']; // campos sensíveis
 *   }
 *
 * Requer a tabela `webc_auditoria` no banco (criar via migration).
 *
 * Schema da tabela webc_auditoria:
 *   - id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
 *   - tabela        VARCHAR(100) NOT NULL    -- nome da tabela auditada
 *   - registro_id   VARCHAR(50)  NOT NULL    -- ID do registro alterado
 *   - acao          ENUM('criado','alterado','excluido') NOT NULL
 *   - payload_antes JSON NULL                -- dados anteriores à alteração
 *   - payload_depois JSON NULL               -- dados após a alteração
 *   - usuario_id    INT UNSIGNED NULL        -- ID do usuário responsável
 *   - usuario_login VARCHAR(100) NULL        -- login do usuário
 *   - ip_origem     VARCHAR(45) NULL         -- IPv4 ou IPv6
 *   - criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 */
trait Auditable
{
    /**
     * Inicializa o trait e registra os event listeners do Eloquent.
     */
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->registerAudit('criado', [], $model->getAttributes()));
        static::updated(fn (Model $model) => $model->registerAudit('alterado', $model->getOriginal(), $model->getAttributes()));
        static::deleted(fn (Model $model) => $model->registerAudit('excluido', $model->getOriginal(), []));
    }

    /**
     * Registra o evento de auditoria na tabela webc_auditoria.
     *
     * @param  string  $action       Ação realizada: criado | alterado | excluido.
     * @param  array   $before       Dados anteriores à operação.
     * @param  array   $after        Dados posteriores à operação.
     */
    private function registerAudit(string $action, array $before, array $after): void
    {
        try {
            $exclude = $this->auditExclude ?? [];

            DB::connection('mysql')->table('webc_auditoria')->insert([
                'tabela'        => $this->getTable(),
                'registro_id'   => (string) $this->getKey(),
                'acao'          => $action,
                'payload_antes' => $this->sanitizePayload($before, $exclude),
                'payload_depois' => $this->sanitizePayload($after, $exclude),
                'usuario_id'    => $this->resolveAuditUserId(),
                'usuario_login' => $this->resolveAuditUserLogin(),
                'ip_origem'     => Request::ip(),
                'criado_em'     => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Falha na auditoria nunca deve interromper a operação principal.
            // O erro é apenas logado para investigação posterior.
            logger()->error('Falha ao registrar auditoria', [
                'model'  => static::class,
                'action' => $action,
                'key'    => $this->getKey(),
            ]);
        }
    }

    /**
     * Remove campos sensíveis e serializa o payload para JSON.
     */
    private function sanitizePayload(array $data, array $exclude): ?string
    {
        if (empty($data)) {
            return null;
        }

        $sanitized = array_diff_key($data, array_flip($exclude));

        return json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Tenta obter o ID do usuário autenticado.
     */
    private function resolveAuditUserId(): ?int
    {
        try {
            return auth('api')->id();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Tenta obter o login do usuário autenticado.
     */
    private function resolveAuditUserLogin(): ?string
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = auth('api')->user();

            return $user?->login ?? $user?->email ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
