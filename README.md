# food99 — Hub de Integracao 99Food (ERP)

> Stack: Laravel 13 · PHP 8.5+ · MySQL 8.4 · Redis
> API interna: `/api/v1/...`

---

## 1. Objetivo

Este projeto integra o ERP com a 99Food, cobrindo:

- autenticacao por loja (escopo por `id_cadastro`)
- catalogo local simples (`menu`, `category`, `item`)
- preview e publicacao de catalogo
- leitura do catalogo publicado na 99Food
- recebimento de webhooks de pedido
- persistencia de pedidos em `mysql_marketplace`
- sincronizacao de pedido para venda no ERP (fila + reprocessamento manual)

Escopo ainda nao implementado:

- catalogo v3 com `modifier_groups` (variacoes complexas)

---

## 2. Arquitetura (estado atual)

Fluxo principal:

`Request -> Route -> Controller -> Service -> Repository -> Model -> MySQL`

Estado atual:

- regra de negocio centralizada em `Services`
- acesso a dados centralizado em `Repositories`
- bindings registrados em [BindsRepositorios.php](app/Services/Extensions/BindsRepositorios.php)

Observacao:

- o reprocessamento manual de pedido roda via `Service`, com validacao de ownership por `id_cadastro`

---

## 3. Rotas

Referencia:

- [routes/api.php](routes/api.php)

### 3.1 Publicas

- `POST /api/v1/login`
- `POST /api/v1/99food/webhook`

### 3.2 Protegidas (JWT)

#### Autenticacao da API

- `GET /api/v1/me`
- `POST /api/v1/refresh`
- `POST /api/v1/logout`

#### Auth 99Food

- `GET /api/v1/food99/auth/shops`
- `POST /api/v1/food99/auth/authorization-url`
- `POST /api/v1/food99/auth/token/get`
- `POST /api/v1/food99/auth/token/refresh`
- `GET /api/v1/food99/auth/token/local/{appShopId}`

#### Catalogo 99Food

- `POST /api/v1/food99/catalog/menus/upsert`
- `GET /api/v1/food99/catalog/menus/{appShopId}`
- `POST /api/v1/food99/catalog/categories/upsert`
- `GET /api/v1/food99/catalog/categories/{appShopId}`
- `POST /api/v1/food99/catalog/items/upsert`
- `GET /api/v1/food99/catalog/items/{appShopId}`
- `POST /api/v1/food99/catalog/categories/link-items`
- `POST /api/v1/food99/catalog/payload/preview`
- `POST /api/v1/food99/catalog/publish`
- `POST /api/v1/food99/catalog/sync-remote`
- `GET /api/v1/food99/catalog/publish/jobs/{appShopId}`

#### Pedidos 99Food

- `GET /api/v1/food99/orders/sync-queue`
- `POST /api/v1/food99/orders/{food99OrderId}/sync-erp`

---

## 4. Autenticacao da API

### Login

`POST /api/v1/login` recebe `login` e `senha`.

- ambos os campos sao obrigatorios; payload invalido retorna `422`;
- somente usuarios ERP com `ativo = A` podem autenticar;
- credenciais invalidas, usuario inativo, inexistente ou hash de senha incompativel retornam o mesmo `401` (`Login invalido`);
- a senha e verificada por `Hash::check()` e nunca e retornada pela API;
- o endpoint aceita no maximo 5 tentativas por minuto para a combinacao de IP e login.

O ERP deve armazenar hashes compativeis com o driver de hash do Laravel (como bcrypt ou Argon2) em uma coluna com tamanho suficiente. Senhas legadas em texto puro ou hashes nao reconhecidos sao rejeitados.

Uma resposta de sucesso contem `access_token`, `token_type` (`bearer`) e `expires_in` em segundos. Com o TTL padrao de 60 minutos, `expires_in` e `3600`.

### Uso e ciclo de vida do JWT

Envie o token nas rotas protegidas:

```http
Authorization: Bearer <access_token>
```

- `GET /api/v1/me` retorna o usuario autenticado sem o campo `senha`;
- `POST /api/v1/refresh` exige token ainda valido, gera outro token e revoga o anterior;
- `POST /api/v1/logout` revoga o token atual e retorna `204`.

## 5. Fluxo de catalogo (simples)

1. `menus/upsert`
2. `categories/upsert`
3. `items/upsert`
4. `categories/link-items` (quando necessario)
5. `payload/preview`
6. `publish`

### Regras de `app_item_id` (atual)

- no `create`, `app_item_id` pode vir vazio
- o backend gera automaticamente um ID estavel (`p{id_produto}_g{id_grade}`, com sufixo se colidir)
- no `update`, se ja existir item para o mesmo `id_produto/id_grade`, o `app_item_id` persistido e preservado
- se `app_item_id` informado ja pertencer a outro produto/grade da mesma loja, retorna `422`

Implementacao:

- [Food99CatalogController.php](app/Http/Controllers/Food99/Catalog/Food99CatalogController.php)
- [Food99CatalogManagementService.php](app/Services/Food99/Catalog/Food99CatalogManagementService.php)

---

## 6. Fluxo de pedidos (webhook -> ERP)

### Eventos suportados

- `orderNew`
- `orderFinish`
- `orderCancel`

### Pipeline

1. 99Food chama `POST /api/v1/99food/webhook` com a assinatura valida
2. payload e headers sao gravados em `food99_webhook_inbound_logs`
3. pedido e itens sao persistidos em `food99_orders` e `food99_order_items`
4. `orderNew` persiste o pedido sem criar venda no ERP
5. `orderFinish` tenta localizar/criar a venda ERP via [Food99OrderErpSyncService.php](app/Services/Food99/Orders/Food99OrderErpSyncService.php)
6. quando a venda e criada, `food99_orders.id_venda` e preenchido e `sync_status` vira `synced`
7. se a criacao da venda falhar, `sync_status` fica `pending_sync` para reprocessamento
8. `orderCancel` marca situacao da venda para `E` quando houver venda vinculada

### Status de sincronizacao (`food99_orders.sync_status`)

- `new_order`
- `pending_sync`
- `synced`
- `canceled`

### Observacao operacional

- `origem_venda` esta temporariamente como `B2W` (enum legado da tabela `venda`)
- ao adicionar `99FOOD` no enum, trocar no service de sync

---

## 7. Sync remoto do catalogo

Endpoint interno:

- `POST /api/v1/food99/catalog/sync-remote`

Endpoints 99Food usados:

- `/v1/item/item/list`

Comportamento:

- consulta o catalogo publicado da loja na 99Food
- persiste/atualiza menu, categoria e item no `marketplace`
- quando item remoto nao tiver mapeamento ERP (`id_produto`/`id_grade`), cria produto no ERP
- busca grade criada automaticamente (trigger de `produto`) e salva o vinculo

---

## 8. Banco de dados

### 8.1 `mysql_marketplace`

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

### 8.2 `base_erp`

Usado na sincronizacao de venda/pedido:

- `cliente`
- `produto`
- `grade`
- `venda`
- `venda_itens`
- `venda_pagamento`
- `venda_informacoes`
- `webc_usuario`

---

## 9. Timezone

Aplicacao configurada para:

- `America/Sao_Paulo` em [config/app.php](config/app.php)

No webhook, timestamps unix sao convertidos de UTC para timezone da app:

- [Food99WebhookService.php](app/Services/Food99/Webhook/Food99WebhookService.php)

---

## 10. Configuracao 99Food

Arquivo:

- [config/services.php](config/services.php)

Variaveis:

```env
FOOD99_BASE_URL=https://openapi.99food.com
FOOD99_APP_ID=...
FOOD99_APP_SECRET=...
FOOD99_TIMEOUT=20
```

O webhook exige o header `didi-header-sign`, calculado como `md5(raw_body + FOOD99_APP_SECRET)`. Assinatura ausente ou invalida retorna `401`; sem `FOOD99_APP_SECRET`, a aplicacao retorna erro de configuracao.

---

## 11. Ambiente local e verificacao

O ambiente Docker inclui aplicacao, worker de fila, MySQL 8.4 e Redis:

```bash
docker compose up -d
```

Configure as conexoes `mysql` (`base_erp`) e `mysql_marketplace` (`marketplace`) no `.env` antes de executar migrations. As migrations criam `webc_usuario`, RBAC e auditoria no ERP; as tabelas `food99_*`, cache, sessoes e filas ficam no marketplace. As demais tabelas de negocio do ERP continuam sendo uma dependencia externa.

Testes disponiveis incluem o fluxo de login, validacao JWT, catalogo, pedidos, webhook e services:

```bash
php artisan test
```

Para popular o ambiente local, execute `php artisan db:seed`. Ele cria os usuarios ERP `admin` (`senha-segura`) e `gerente` (`senha-gerente`), vincula o perfil administrador e popula o catalogo sandbox.

O workflow de CI valida Composer, Pint, testes, geracao OpenAPI e build do frontend.

---

## 12. Pendencias tecnicas mapeadas

1. adicionar `origem_venda = 99FOOD` no enum da tabela `venda` e remover workaround `B2W`
2. decidir estrategia final de `app_item_id` para longo prazo (sem quebrar IDs ja publicados)

---

API Generica — Integracao 99Food
