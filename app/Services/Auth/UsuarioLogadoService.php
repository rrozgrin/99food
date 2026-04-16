<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * Serviço para manipulação do usuário autenticado via JWT.
 *
 * Fornece métodos para acessar dados do usuário logado de forma
 * tipada e segura, sem precisar acessar o guard diretamente.
 *
 * Utilizado pelos Services e Repositories que precisam identificar
 * o usuário que está realizando a operação (ex: auditoria, permissões).
 *
 * Exemplo de uso via injeção de dependência:
 *
 *     class PedidoService
 *     {
 *         public function __construct(
 *             private readonly UsuarioLogadoService $usuarioLogado,
 *         ) {}
 *
 *         public function criarPedido(): Pedido
 *         {
 *             $idUsuario = $this->usuarioLogado->getIdUsuarioLogado();
 *             // ...
 *         }
 *     }
 *
 * @see \App\Http\Middleware\ApiJwtMiddleware — Middleware que autentica o JWT antes
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class UsuarioLogadoService
{
    /**
     * Atributos do usuário autenticado (ou null se não logado).
     *
     * @var array<string, mixed>|null
     */
    protected readonly ?array $usuario;

    /**
     * Carrega os atributos do usuário autenticado via guard 'api' (JWT).
     * Se não houver usuário autenticado, define como null.
     */
    public function __construct()
    {
        $user = Auth::guard('api')->user();
        $this->usuario = $user?->getAttributes();
    }

    /**
     * Retorna todos os atributos do usuário autenticado.
     *
     * @return array<string, mixed>|null
     */
    public function getUsuario(): ?array
    {
        return $this->usuario;
    }

    /**
     * Retorna o ID do cadastro vinculado ao usuário logado.
     */
    public function getIdCadastroLogado(): ?int
    {
        return $this->usuario['id_cadastro'] ?? null;
    }

    /**
     * Retorna o ID do usuário autenticado.
     */
    public function getIdUsuarioLogado(): ?int
    {
        return $this->usuario['id'] ?? null;
    }

    /**
     * Retorna o login do usuário autenticado.
     */
    public function getLogin(): ?string
    {
        return $this->usuario['login'] ?? null;
    }

    /**
     * Retorna o ID do funcionário vinculado ao usuário.
     */
    public function getIdFuncionario(): ?int
    {
        return $this->usuario['id_funcionario'] ?? null;
    }

    /**
     * Retorna a string de logon composta (login + senha).
     *
     * Formato: "{login}S{senha}"
     */
    public function getLogon(): ?string
    {
        $login = $this->getLogin();
        $senha = $this->usuario['senha'] ?? null;

        return ($login !== null && $senha !== null)
            ? $login . 'S' . $senha
            : null;
    }

    /**
     * Verifica se o usuário autenticado é um login master.
     */
    public function isLoginMaster(): bool
    {
        return ($this->usuario['login_master'] ?? '') === 'S';
    }
}
