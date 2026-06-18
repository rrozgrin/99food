<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Model do Usuário ERP.
 *
 * Mapeia para a tabela 'webc_usuario' no banco 'base_erp'.
 * Implementa JWTSubject para suporte à autenticação via JWT.
 *
 * Colunas de timestamp customizadas (padrão do banco legado):
 *  - data_criacao  → CREATED_AT
 *  - data_alteracao → UPDATED_AT
 *
 * NOTA: A coluna 'senha' armazena a senha em texto plano (legado).
 * Deve ser migrada para bcrypt/argon2 no futuro.
 *
 * @see \App\Http\Controllers\AuthController — Controller de autenticação
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'api_key', 'senha'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** TTL do cache de permissões por usuário: 15 minutos. */
    private const PERMISSIONS_CACHE_TTL = 900;

    /**
     * Nome da tabela no banco de dados.
     */
    protected $table = 'base_erp.webc_usuario';

    /**
     * Nome da coluna de criação (CREATED_AT).
     */
    const CREATED_AT = 'data_criacao';

    /**
     * Nome da coluna de atualização (UPDATED_AT).
     */
    const UPDATED_AT = 'data_alteracao';

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // JWT
    // -------------------------------------------------------------------------

    /**
     * Obtém o identificador que será armazenado no claim 'sub' do JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Retorna claims customizados para adicionar ao payload do JWT.
     * Inclui roles do usuário para reduzir consultas ao banco por request.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
        // return [
        //     'roles' => $this->roles->pluck('nome')->toArray(),
        // ];
    }

    // -------------------------------------------------------------------------
    // RBAC — Roles e Permissões
    // -------------------------------------------------------------------------

    /**
     * Roles associados ao usuário.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Role::class,
            table: 'webc_usuario_role',
            foreignPivotKey: 'usuario_id',
            relatedPivotKey: 'role_id',
        );
    }

    /**
     * Verifica se o usuário possui uma permissão específica.
     * As permissões são cacheadas por 15 minutos para evitar queries por request.
     *
     * @param  string $permission  Permissão no formato 'modulo.acao' (ex: 'clientes.editar').
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getCachedPermissions();

        return in_array($permission, $permissions, strict: true)
            || in_array('admin.tudo', $permissions, strict: true);
    }

    /**
     * Verifica se o usuário possui um role específico.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles->contains('nome', $role);
    }

    /**
     * Invalida o cache de permissões deste usuário.
     * Chamar após alterar roles ou permissões do usuário.
     */
    public function invalidatePermissionsCache(): void
    {
        Cache::forget($this->permissionsCacheKey());
    }

    /**
     * Retorna a lista de permissões do usuário, com cache.
     *
     * @return string[]
     */
    private function getCachedPermissions(): array
    {
        return Cache::remember(
            $this->permissionsCacheKey(),
            self::PERMISSIONS_CACHE_TTL,
            fn () => $this->roles
                ->load('permissions')
                ->flatMap(fn (Role $role) => $role->permissions->pluck('nome'))
                ->unique()
                ->values()
                ->all(),
        );
    }

    /**
     * Gera a chave de cache para as permissões deste usuário.
     */
    private function permissionsCacheKey(): string
    {
        return "usuario:{$this->getKey()}:permissoes";
    }
}
