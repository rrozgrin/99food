<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiJwtMiddlewareTest extends TestCase
{
    #[Test]
    public function deve_bloquear_rota_protegida_sem_token_jwt(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $response->assertJsonPath('code', 401);
        $response->assertJsonPath('conteudo', 'token-error');
        $response->assertJsonPath('msg', 'Token de autorização não encontrado.');
    }
}

