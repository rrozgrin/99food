# Integration Hub API

API de integração entre um ERP legado e a 99Food, construída em Laravel 13. O projeto cobre autenticação por loja, publicação de catálogo, ingestão de webhooks, persistência local e sincronização assíncrona de pedidos com o ERP.

> Stack: PHP 8.5+, Laravel 13, MySQL 8.4, Redis, JWT, OpenAPI/Swagger

## Visão Geral

Este projeto foi desenhado para resolver um problema real de integração:

- autenticar lojas individualmente na 99Food
- manter um catálogo local simplificado (`menu`, `category`, `item`)
- gerar preview e publicar catálogo na API externa
- receber webhooks de pedidos com persistência e rastreabilidade
- sincronizar pedidos com o ERP via fila, com reprocessamento manual e foco em idempotência

## Arquitetura

Fluxo principal:

`Request -> Route -> Controller -> Service -> Repository -> Model -> MySQL`

Decisões de estrutura:

- controllers enxutos, com validação de entrada e delegação para services
- regra de negócio concentrada em `app/Services`
- acesso a dados encapsulado em `Repositories`
- separação por domínio em `Food99` e `BaseErp`
- respostas padronizadas e tratamento global de exceções
- documentação OpenAPI disponível em rota própria

Referências úteis:

- [Rotas da API](routes/api.php)
- [Bindings de repositórios](app/Services/Extensions/BindsRepositorios.php)
- [Especificação OpenAPI](app/OpenApi/OpenApiSpec.php)

## Fluxos Principais

### Catálogo

1. `POST /api/v1/food99/catalog/menus/upsert`
2. `POST /api/v1/food99/catalog/categories/upsert`
3. `POST /api/v1/food99/catalog/items/upsert`
4. `POST /api/v1/food99/catalog/categories/link-items`
5. `POST /api/v1/food99/catalog/payload/preview`
6. `POST /api/v1/food99/catalog/publish`

Destaques:

- geração automática de `app_item_id` estável
- validação de colisão por loja
- suporte a sincronização remota do catálogo já publicado

### Pedidos

1. 99Food envia `POST /api/v1/99food/webhook`
2. o payload é gravado em `food99_webhook_inbound_logs`
3. pedido e itens são persistidos localmente
4. `orderNew` dispara [SyncFood99OrderToErpJob.php](app/Jobs/Food99/SyncFood99OrderToErpJob.php)
5. a fila executa [Food99OrderErpSyncService.php](app/Services/Food99/Orders/Food99OrderErpSyncService.php)
6. o pedido é convertido em venda no ERP
7. eventos posteriores atualizam o status operacional da venda

## Diferenciais Técnicos

- autenticação JWT para rotas internas
- autenticação por loja para integração com terceiro
- fila para processamento assíncrono
- cenários de idempotência cobertos por testes
- documentação OpenAPI + página interativa
- suporte a legado com tabelas e convenções antigas do ERP

## Estrutura de Dados

Banco `mysql_marketplace`:

- `food99_app_credentials`
- `food99_shops`
- `food99_shop_tokens`
- `food99_shop_menus`
- `food99_shop_categories`
- `food99_shop_items`
- `food99_shop_category_items`
- `food99_publish_jobs`
- `food99_webhook_inbound_logs`
- `food99_orders`
- `food99_order_items`

Banco `base_erp`:

- `cliente`
- `produto`
- `grade`
- `venda`
- `venda_itens`
- `venda_pagamento`
- `venda_informacoes`
- `webc_usuario`

## Execução Local

Pré-requisitos:

- PHP 8.5+
- Composer
- Node.js
- acesso aos bancos usados pela integração
- Redis para fila/cache

Comandos principais:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Para desenvolvimento:

```bash
composer run dev
```

## Testes

Suite disponível em `tests/` com cobertura para:

- autenticação
- publicação de catálogo
- sincronização de pedidos
- idempotência
- value objects

Execução:

```bash
composer test
```

## Documentação

- API interativa: `/docs/api`
- Guia técnico do ecossistema: `/docs/ecossistema`
- JSON OpenAPI: `/docs/openapi.json`

## Limitações Conhecidas

- parte do domínio depende de estruturas legadas do ERP
- ainda existe compatibilidade com dados antigos, como nomes de tabela `webc_`
- o escopo atual não cobre catálogo avançado com `modifier_groups`

## Objetivo do Projeto

Este repositório demonstra arquitetura de API, integração com sistema externo, convivência com legado, uso de filas, documentação e testes em um cenário de negócio menos trivial que um CRUD comum.
