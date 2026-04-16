# food99 — Hub de Integracao 99Food (ERP Hub)

> Stack: Laravel 13 · PHP 8.5+ · MySQL 8.4 · Redis  
> API interna: `/api/v1/...`

---

## 1. Objetivo

Este projeto integra o ERP ERP Hub com a 99Food, cobrindo:

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

## 4. Fluxo de catalogo (simples)

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

## 5. Fluxo de pedidos (webhook -> ERP)

### Eventos suportados

- `orderNew`
- `orderFinish`
- `orderCancel`

### Pipeline

1. 99Food chama `POST /api/v1/99food/webhook`
2. payload e headers sao gravados em `food99_webhook_inbound_logs`
3. pedido e itens sao persistidos em `food99_orders` e `food99_order_items`
4. `orderNew` enfileira [SyncFood99OrderToErpJob.php](app/Jobs/Food99/SyncFood99OrderToErpJob.php)
5. job executa [Food99OrderErpSyncService.php](app/Services/Food99/Orders/Food99OrderErpSyncService.php)
6. venda ERP e criada e `food99_orders.id_venda` e preenchido
7. `orderFinish` marca situacao da venda para `C`
8. `orderCancel` marca situacao da venda para `E`

### Status de sincronizacao (`food99_orders.sync_status`)

- `pending_erp`
- `processing_erp`
- `synced_erp`
- `failed_erp`
- `pending_finish_erp`
- `finished_erp`
- `pending_cancel_erp`
- `canceled_erp`

### Observacao operacional

- `origem_venda` esta temporariamente como `B2W` (enum legado da tabela `venda`)
- ao adicionar `99FOOD` no enum, trocar no service de sync

---

## 6. Sync remoto do catalogo

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

## 7. Banco de dados

### 7.1 `mysql_marketplace`

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

### 7.2 `base_erp`

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

## 8. Timezone

Aplicacao configurada para:

- `America/Sao_Paulo` em [config/app.php](config/app.php)

No webhook, timestamps unix sao convertidos de UTC para timezone da app:

- [Food99WebhookService.php](app/Services/Food99/Webhook/Food99WebhookService.php)

---

## 9. Configuracao 99Food

Arquivo:

- [config/services.php](config/services.php)

Variaveis:

```env
FOOD99_BASE_URL=https://openapi.99food.com
FOOD99_APP_ID=...
FOOD99_APP_SECRET=...
FOOD99_TIMEOUT=20
FOOD99_WEBHOOK_VERIFY_SIGNATURE=false
```



---

API ERP Hub — Integracao 99Food
