# Postman - Rotas 99Food (Completo)

Este documento lista as rotas atuais para teste no Postman, com exemplos de body e parametros.

## 1) Variaveis sugeridas no Postman

```text
base_url=http://localhost:8000
access_token=SEU_JWT
app_shop_id=wc-sandbox-002
food99_order_id=1
```

## 2) Autenticacao usada

- Rotas `publicas`: sem `Bearer`
- Rotas `protegidas`: `Authorization: Bearer {{access_token}}`

---

## 3) Rotas Publicas

### 3.1 Login

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/login`
- Auth: `None`
- Body (JSON):

```json
{
  "usuario": "SEU_USUARIO",
  "senha": "SUA_SENHA"
}
```

### 3.2 Webhook 99Food

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/99food/webhook`
- Auth: `None`
- Header obrigatorio:
  - `Content-Type: application/json`
  - `didi-header-sign: <assinatura>`

#### Exemplo `orderNew`

```json
{
  "app_id": 5764607788456609652,
  "app_shop_id": "wc-sandbox-002",
  "type": "orderNew",
  "timestamp": 1776185616,
  "data": {
    "order_id": 5764607775769495278,
    "order_info": {
      "order_id": 5764607775769495278,
      "status": 100,
      "order_index": 1,
      "country": "BR",
      "timezone": "America/Sao_Paulo",
      "pay_type": 1,
      "delivery_type": 1,
      "create_time": 1776185616,
      "pay_time": 1776185616,
      "price": {
        "order_price": 2299,
        "real_price": 2299,
        "real_pay_price": 2299,
        "refund_price": 0
      },
      "receive_address": {
        "name": "DiDi",
        "phone": ""
      },
      "order_items": [
        {
          "app_item_id": "p1017855840_g1028102882",
          "name": "Hamburguer Cheddar",
          "amount": 1,
          "sku_price": 2299,
          "total_price": 2299,
          "real_price": 2299
        }
      ]
    }
  }
}
```

#### Exemplo `orderFinish`

```json
{
  "app_id": 5764607788456609652,
  "app_shop_id": "wc-sandbox-002",
  "type": "orderFinish",
  "timestamp": 1776090319,
  "data": {
    "order_id": 5764607594156132035
  }
}
```

#### Exemplo `orderCancel`

```json
{
  "app_id": 5764607788456609652,
  "app_shop_id": "wc-sandbox-002",
  "type": "orderCancel",
  "timestamp": 1776090390,
  "data": {
    "order_id": 5764607594156132035
  }
}
```

---

## 4) Rotas Protegidas Gerais

### 4.1 Usuario autenticado

- Metodo: `GET`
- URL: `{{base_url}}/api/v1/me`
- Auth: `Bearer`

### 4.2 Refresh JWT

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/refresh`
- Auth: `Bearer`

---

## 5) 99Food - Auth

### 5.1 Listar lojas

- Metodo: `GET`
- URL: `{{base_url}}/api/v1/food99/auth/shops`
- Auth: `Bearer`

### 5.2 Gerar URL de autorizacao

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/auth/authorization-url`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "wc-sandbox-002",
  "shop_name": "Loja Sandbox"
}
```

### 5.3 Obter token da loja

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/auth/token/get`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "{{app_shop_id}}"
}
```

### 5.4 Refresh token da loja

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/auth/token/refresh`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "{{app_shop_id}}"
}
```

### 5.5 Consultar token local

- Metodo: `GET`
- URL: `{{base_url}}/api/v1/food99/auth/token/local/{{app_shop_id}}`
- Auth: `Bearer`

---

## 6) 99Food - Catalogo

### 6.1 Upsert menu

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/catalog/menus/upsert`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "{{app_shop_id}}",
  "app_menu_id": "menu_principal",
  "menu_name": "Menu Principal",
  "sort_order": 0,
  "is_active": true,
  "metadata": {}
}
```

### 6.2 Listar menus

- Metodo: `GET`
- URL local: `{{base_url}}/api/v1/food99/catalog/menus/{{app_shop_id}}`
- URL published: `{{base_url}}/api/v1/food99/catalog/menus/{{app_shop_id}}?view=published`
- Auth: `Bearer`

### 6.3 Upsert categoria

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/catalog/categories/upsert`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "{{app_shop_id}}",
  "app_menu_id": "menu_principal",
  "app_category_id": "burgers",
  "category_name": "Hamburgueres",
  "sort_order": 0,
  "is_active": true,
  "metadata": {}
}
```

### 6.4 Listar categorias

- Metodo: `GET`
- URL local: `{{base_url}}/api/v1/food99/catalog/categories/{{app_shop_id}}`
- URL published: `{{base_url}}/api/v1/food99/catalog/categories/{{app_shop_id}}?view=published`
- Auth: `Bearer`

### 6.5 Upsert item

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/catalog/items/upsert`
- Auth: `Bearer`

#### Exemplo create (backend gera `app_item_id`)

```json
{
  "app_shop_id": "{{app_shop_id}}",
  "app_category_id": "burgers",
  "id_produto": 1001,
  "id_grade": 1001,
  "item_name": "Hamburguer Cheddar",
  "short_desc": "Pao, carne e queijo cheddar",
  "price_source": "grade",
  "price_amount": 22.99,
  "is_active": true,
  "publish_status": "draft"
}
```

#### Exemplo update (com `app_item_id`)

```json
{
  "app_shop_id": "{{app_shop_id}}",
  "app_category_id": "burgers",
  "app_item_id": "p1001_g1001",
  "id_produto": 1001,
  "id_grade": 1001,
  "item_name": "Hamburguer Cheddar",
  "short_desc": "Pao, carne e queijo cheddar",
  "price_source": "grade",
  "price_amount": 22.99,
  "is_active": true,
  "publish_status": "draft"
}
```

### 6.6 Listar itens

- Metodo: `GET`
- URL local: `{{base_url}}/api/v1/food99/catalog/items/{{app_shop_id}}`
- URL published: `{{base_url}}/api/v1/food99/catalog/items/{{app_shop_id}}?view=published`
- Auth: `Bearer`

### 6.7 Vincular itens em categoria

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/catalog/categories/link-items`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "{{app_shop_id}}",
  "app_category_id": "burgers",
  "app_item_ids": ["p1001_g1001", "p1002_g1002"]
}
```

### 6.8 Preview do payload

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/catalog/payload/preview`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "{{app_shop_id}}"
}
```

### 6.9 Publicar catalogo

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/catalog/publish`
- Auth: `Bearer`

#### Publicacao completa

```json
{
  "app_shop_id": "{{app_shop_id}}"
}
```

#### Publicacao seletiva

```json
{
  "app_shop_id": "{{app_shop_id}}",
  "app_item_ids": ["p1001_g1001"]
}
```

### 6.10 Sync remoto do catalogo publicado

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/catalog/sync-remote`
- Auth: `Bearer`
- Body (JSON):

```json
{
  "app_shop_id": "{{app_shop_id}}"
}
```

### 6.11 Historico de jobs de publicacao

- Metodo: `GET`
- URL: `{{base_url}}/api/v1/food99/catalog/publish/jobs/{{app_shop_id}}`
- Auth: `Bearer`

---

## 7) 99Food - Pedidos

### 7.1 Fila de sync ERP

- Metodo: `GET`
- URL basica: `{{base_url}}/api/v1/food99/orders/sync-queue`
- Auth: `Bearer`

#### Com filtros

`{{base_url}}/api/v1/food99/orders/sync-queue?statuses[]=pending_erp&statuses[]=failed_erp&limit=50&app_shop_id={{app_shop_id}}`

### 7.2 Detalhe de pedido

- Metodo: `GET`
- URL: `{{base_url}}/api/v1/food99/orders/{{food99_order_id}}`
- Auth: `Bearer`

### 7.3 Reprocessar pedido no ERP

- Metodo: `POST`
- URL: `{{base_url}}/api/v1/food99/orders/{{food99_order_id}}/sync-erp`
- Auth: `Bearer`
- Body: vazio

---

## 8) 99Food - Logs de Webhook

### 8.1 Listar logs de webhook

- Metodo: `GET`
- URL basica: `{{base_url}}/api/v1/food99/webhooks/logs`
- Auth: `Bearer`

#### Com filtros

`{{base_url}}/api/v1/food99/webhooks/logs?app_shop_id={{app_shop_id}}&event_names[]=orderNew&statuses[]=failed&limit=50`

#### Parametros de query disponiveis

| Parametro | Tipo | Valores aceitos | Default |
|-----------|------|-----------------|---------|
| `app_shop_id` | string | qualquer | todos |
| `event_names[]` | array | `orderNew`, `orderFinish`, `orderCancel`, `unknown` | todos |
| `statuses[]` | array | `received`, `processed`, `failed` | todos |
| `limit` | int | 1-200 | 50 |

---

## 9) Retornos esperados uteis

- Webhook processado:

```json
{
  "errno": 0,
  "errmsg": "ok"
}
```

- Resposta padrao interna:
  - `code: 200` sucesso
  - `code: 4xx/5xx` erro de validacao/regra/infra
