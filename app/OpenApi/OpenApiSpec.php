<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Especificação OpenAPI principal da Integration Hub API.
 *
 * Este arquivo define os metadados globais da documentação Swagger:
 * - Informações do projeto (título, versão, contato)
 * - Servidor(es) da API
 * - Tags para agrupamento de endpoints
 * - Schemas reutilizáveis de resposta padrão
 *
 * 📌 Acesse a documentação interativa em:
 *    https://integration-hub-api.test/api/documentation
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Integration Hub API',
    description: <<<'DESC'
    API RESTful para integração entre ERP legado e 99Food.

    ## Autenticação
    A API utiliza **JWT Bearer Token**. Para autenticar:
    1. Faça `POST /api/v1/login` com `login` e `senha`
    2. Copie o `access_token` da resposta
    3. Clique em **Authorize** (🔒) e cole: `Bearer {seu_token}`

    ## Padrão de Resposta
    A maior parte das respostas segue o formato:
    ```json
    {
        "conteudo": "...",
        "msg": "",
        "code": 200
    }
    ```

    Exceção:
    - os endpoints de autenticação que retornam token (`/login` e `/refresh`) respondem com o payload JWT direto

    ## Códigos de Status
    | Código | Significado |
    |--------|-------------|
    | 200    | Sucesso |
    | 201    | Criado com sucesso |
    | 400    | Requisição inválida |
    | 401    | Não autenticado (token inválido/expirado) |
    | 403    | Sem permissão |
    | 404    | Recurso não encontrado |
    | 422    | Erro de validação |
    | 500    | Erro interno do servidor |
    DESC,
    contact: new OA\Contact(
        name: 'Rafael Rozgrin',
        email: 'rrozgrin@gmail.com',
    ),
)]
#[OA\Server(
    url: '/api/v1',
    description: 'Integration Hub API v1',
)]

// =========================================================================
// Tags — Agrupamento de endpoints na documentação
// =========================================================================
#[OA\Tag(
    name: 'Autenticação',
    description: 'Login, renovação de token e dados do usuário autenticado',
)]
#[OA\Tag(
    name: '99Food - Auth',
    description: 'Autenticacao por loja e vinculacao de estabelecimentos na 99Food',
)]
#[OA\Tag(
    name: '99Food - Catalog',
    description: 'Montagem e publicacao de catalogo (menus, categorias e itens) na 99Food',
)]

// =========================================================================
// Schemas reutilizáveis — Padrão de resposta da API
// =========================================================================
#[OA\Schema(
    schema: 'RespostaPadrao',
    title: 'Resposta Padrão',
    description: 'Formato padrão de todas as respostas da Integration Hub API.',
    required: ['conteudo', 'msg', 'code'],
    properties: [
        new OA\Property(
            property: 'conteudo',
            description: 'Dados retornados pela operação (objeto, array ou string vazia)',
            type: 'object',
        ),
        new OA\Property(
            property: 'msg',
            description: 'Mensagem descritiva (vazio em caso de sucesso)',
            type: 'string',
            example: '',
        ),
        new OA\Property(
            property: 'code',
            description: 'Código HTTP da resposta',
            type: 'integer',
            example: 200,
        ),
    ],
)]
#[OA\Schema(
    schema: 'RespostaErro',
    title: 'Resposta de Erro',
    description: 'Formato de resposta em caso de erro.',
    required: ['conteudo', 'msg', 'code'],
    properties: [
        new OA\Property(
            property: 'conteudo',
            description: 'Sempre vazio em erros',
            type: 'string',
            example: '',
        ),
        new OA\Property(
            property: 'msg',
            description: 'Mensagem descritiva do erro',
            type: 'string',
            example: 'Recurso não encontrado.',
        ),
        new OA\Property(
            property: 'code',
            description: 'Código HTTP do erro',
            type: 'integer',
            example: 404,
        ),
    ],
)]
#[OA\Schema(
    schema: 'RespostaErroDetalhado',
    title: 'Resposta de Erro Detalhado (dev)',
    description: 'Em ambiente de desenvolvimento, erros incluem detalhes extras para debug.',
    required: ['conteudo', 'msg', 'code'],
    properties: [
        new OA\Property(property: 'conteudo', type: 'string', example: ''),
        new OA\Property(property: 'msg', type: 'string', example: 'SQLSTATE[42S02]: Base table not found'),
        new OA\Property(property: 'code', type: 'integer', example: 500),
        new OA\Property(property: 'file', type: 'string', example: 'app/Repository/Eloquent/EloquentRepository.php'),
        new OA\Property(property: 'line', type: 'integer', example: 42),
        new OA\Property(property: 'exception', type: 'string', example: 'QueryException'),
        new OA\Property(property: 'trace', type: 'string'),
    ],
)]
#[OA\Schema(
    schema: 'TokenJWT',
    title: 'Token JWT',
    description: 'Resposta contendo o token JWT de autenticação.',
    required: ['access_token', 'token_type', 'expires_in'],
    properties: [
        new OA\Property(
            property: 'access_token',
            type: 'string',
            example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
        ),
        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', example: 3600, description: 'Tempo de expiração em segundos'),
    ],
)]

// Classe vazia — apenas carrega as anotações OpenAPI globais
#[OA\Tag(
    name: 'Produto',
    description: 'Operações de Produto',
)]
class OpenApiSpec {}
