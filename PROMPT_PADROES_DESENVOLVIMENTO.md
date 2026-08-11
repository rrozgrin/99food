# 📋 PROMPT — Padrões de Desenvolvimento do Food99 Integration Hub

> **Versão:** 3.1
> **Autor:** Rafael Rozgrin <rrozgrin@gmail.com>
> **Stack:** Laravel 13 • PHP 8.5+ • MySQL 8.4
> **Objetivo:** Guia definitivo de padrões para que qualquer programador entenda e siga a arquitetura do projeto.

> **Nota de estado atual (99Food):**
> - A integracao 99Food ja possui fluxo real de autenticacao por loja escopado por `id_cadastro`.
> - O catalogo implementado hoje e o fluxo simples (menu/categoria/item), sem `modifier_groups` persistidos.
> - O webhook de pedidos (`orderNew`, `orderFinish`, `orderCancel`) ja persiste em `mysql_marketplace` e sincroniza com o ERP via fila (`SyncFood99OrderToErpJob`).
> - O endpoint `catalog/items/upsert` aceita `app_item_id` opcional: quando vazio, o backend gera ID estavel e preserva o existente em updates.
> - O projeto foi refatorado para Repository-first no dominio 99Food; `Services` nao usam `DB::table()` diretamente.

---

## Sumário

1. [Arquitetura em Camadas](#1-arquitetura-em-camadas)
2. [Comparativo apidefault vs Food99 Integration Hub](#2-comparativo-apidefault-vs-food99-integration-hub)
3. [Estrutura de Diretórios](#3-estrutura-de-diretrios)
4. [Value Objects — Precisão Fiscal](#4-value-objects--preciso-fiscal)
5. [Eloquent Casts para Value Objects](#5-eloquent-casts-para-value-objects)
6. [Model](#6-model)
7. [Padrão de Resposta da API](#7-padro-de-resposta-da-api)
8. [Repository — CRUD Base](#8-repository--crud-base)
9. [DataTables — Suporte Opt-in](#9-datatables--suporte-opt-in)
10. [Service — Lógica de Negócio](#10-service--lgica-de-negcio)
11. [DTO — Data Transfer Object](#11-dto--data-transfer-object)
12. [Controller](#12-controller)
13. [Exceções](#13-excees)
14. [Autenticação JWT](#14-autenticao-jwt)
15. [RBAC — Controle de Acesso](#15-rbac--controle-de-acesso)
16. [Rotas da API](#16-rotas-da-api)
17. [Swagger / OpenAPI](#17-swagger--openapi)
18. [Cache com Redis](#18-cache-com-redis)
19. [Calculadora Fiscal](#19-calculadora-fiscal)
20. [Domain Events](#20-domain-events)
21. [Audit Trail](#21-audit-trail)
22. [Gerenciamento de Transações](#22-gerenciamento-de-transaes)
23. [Rate Limiting e Filas](#23-rate-limiting-e-filas)
24. [Comandos Artisan Customizados](#24-comandos-artisan-customizados)
25. [Hierarquia de Pastas nos Comandos](#25-hierarquia-de-pastas-nos-comandos)
26. [Convenções de Código](#26-convenes-de-cdigo)
27. [Checklist — Criando um Novo Domínio](#27-checklist--criando-um-novo-domnio)

---

## 1. Arquitetura em Camadas

Cada requisição percorre as camadas de forma **sequencial e obrigatória**. Nenhuma camada pode pular outra.

```
Request HTTP → Route → Controller → Service → Repository → Model → MySQL
```

| Camada         | Responsabilidade                                              | Localização              |
|----------------|---------------------------------------------------------------|--------------------------|
| **Route**      | Define endpoints e middlewares. Sem lógica.                   | `routes/api.php`         |
| **Controller** | Recebe a request, delega ao Service, retorna resposta. Enxuto.| `app/Http/Controllers/`  |
| **Service**    | TODA lógica de negócio. Validações, regras, orquestração.     | `app/Services/`          |
| **Repository** | Acesso ao banco via Eloquent. Sem lógica de negócio.          | `app/Repository/`        |
| **Model**      | Representação da tabela. Fillable, casts, relacionamentos.    | `app/Models/`            |

### ⛔ Regras Absolutas

- Controllers **NÃO** podem acessar Repositories diretamente.
- Services **NÃO** podem usar `DB::table()` ou queries diretas.
- Models **NÃO** podem ter lógica de negócio.
- Repositories **NÃO** podem lançar exceções de negócio — apenas retornar dados.
- Exceções de negócio são lançadas **apenas nos Services**.

---

## 2. Comparativo apidefault vs Food99 Integration Hub

| Comando                          | Gera                                                       |
|----------------------------------|-------------------------------------------------------------|
| `make:domain Nome`               | Model + DTO + Repository + Service + Controller + Tag + Bind |
| `make:domain Nome --datatables`  | Tudo acima + HasDataTables no Repository                     |
| `make:domain Nome --table=webc_x`| Tudo acima com tabela customizada                            |
| `make:repository Nome`           | Interface + Eloquent + Binding                               |
| `make:repository Nome --datatables` | Repository com HasDataTables + DataTablesInterface        |
| `make:service Nome`              | Service com Repository + UsuarioLogado + Converter           |
| `make:dto Nome`                  | DTO readonly com OA\Schema + RequestBodyConverterInterface   |

### Exemplos

```bash
# Domínio completo simples
php artisan make:domain Produto --table=webc_produto

# Domínio com DataTables
php artisan make:domain Produto --table=webc_produto --datatables

# Domínio com hierarquia de pastas
php artisan make:domain BaseErp\\Cadastros\\Cliente --table=webc_cliente

# Apenas repository
php artisan make:repository Produto

# Apenas service
php artisan make:service Produto

# Apenas DTO
php artisan make:dto Produto
```

### O que cada comando faz

**`make:domain Produto --table=webc_produto`:**
1. Cria `app/Models/Produto/Produto.php` com `$table = 'webc_produto'`
2. Cria `app/DTO/Produto/ProdutoDTO.php` com `OA\Schema`
3. Cria `app/Repository/Contracts/Models/Produto/ProdutoRepositoryInterface.php`
4. Cria `app/Repository/Eloquent/Models/Produto/ProdutoEloquentRepository.php`
5. Registra binding no `BindsRepositorios.php`
6. Cria `app/Services/Produto/ProdutoService.php`
7. Cria `app/Http/Controllers/Produto/ProdutoController.php` com anotações Swagger
8. Registra tag Swagger no `OpenApiSpec.php`
9. Exibe sugestão de rotas no terminal

---

## 3. Estrutura de Diretórios

A trait `Auditable` registra automaticamente **quem criou, alterou ou excluiu** qualquer registro.

### Ativando a Auditoria no Model

```php
use App\Models\Traits\Auditable;

class Produto extends Model
{
    use Auditable;

    // Campos que NÃO devem ser auditados (ex: senhas, tokens)
    protected array $auditExclude = ['senha', 'token_reset'];

    // ... resto do model
}
```

### O que é registrado

A tabela `webc_auditoria` armazena:

| Campo           | Descrição                                    |
|-----------------|----------------------------------------------|
| `acao`          | `criado`, `atualizado`, `deletado`           |
| `tabela`        | Nome da tabela auditada                      |
| `registro_id`   | ID do registro afetado                       |
| `usuario_id`    | ID do usuário que fez a ação (do JWT)        |
| `payload_antes` | JSON com valores ANTES da alteração          |
| `payload_depois`| JSON com valores DEPOIS da alteração         |
| `ip`            | IP da requisição                             |
| `criado_em`     | Timestamp UTC da ação                        |

### Consultando histórico

```php
// Histórico de um produto específico
DB::table('webc_auditoria')
    ->where('tabela', 'webc_produto')
    ->where('registro_id', $produtoId)
    ->orderBy('criado_em', 'desc')
    ->get();
```

> ⚠️ **Segurança:** Nunca retorne dados da `webc_auditoria` diretamente na API pública.

---

## 4. Value Objects — Precisão Fiscal

Os comandos `make:*` suportam hierarquia de pastas via separador `\`. O **último segmento** é o nome da entidade; os anteriores viram diretórios.

| Entrada                                | entityName | Diretórios              | Namespace                              |
|-----------------------------------------|------------|-------------------------|----------------------------------------|
| `Produto`                               | Produto    | `Produto/`              | `App\...\Produto`                      |
| `BaseErp\\Produtos\\Produto`     | Produto    | `BaseErp/Produtos/` | `App\...\BaseErp\Produtos`   |
| `BaseErp\\Cadastros\\Cliente`    | Cliente    | `BaseErp/Cadastros/` | `App\...\BaseErp\Cadastros` |

### Exemplo visual

```bash
# Comando:
php artisan make:domain BaseErp\\Cadastros\\Cliente --table=webc_cliente

# Arquivos gerados:
app/Models/BaseErp/Cadastros/Cliente.php
app/DTO/BaseErp/Cadastros/ClienteDTO.php
app/Repository/Contracts/Models/BaseErp/Cadastros/ClienteRepositoryInterface.php
app/Repository/Eloquent/Models/BaseErp/Cadastros/ClienteEloquentRepository.php
app/Services/BaseErp/Cadastros/ClienteService.php
app/Http/Controllers/BaseErp/Cadastros/ClienteController.php
```

---

## 5. Eloquent Casts para Value Objects

1. ☐ Execute `php artisan make:domain NomeEntidade --table=webc_tabela`
2. ☐ Edite o **Model** — configure `$fillable`, `casts()` com `MoneyCast::class`, relacionamentos e `use Auditable` se necessário
3. ☐ Edite o **DTO** — adicione propriedades com `#[OA\Property]` (campos monetários como `string`)
4. ☐ Edite a **Interface** do Repository — adicione métodos específicos do domínio
5. ☐ Implemente os métodos no **Eloquent Repository** (adicione `use CacheableRepository` se necessário)
6. ☐ Edite o **Service** — descomente `criar()`, `atualizar()`, `remover()` (a trait `WithTransaction` já está inclusa pelo `make:service`)
7. ☐ Defina as **permissões RBAC** necessárias (ex: `produto.ver`, `produto.editar`)
8. ☐ Edite o **Controller** — descomente as ações e anotações Swagger
9. ☐ Adicione as **rotas** em `routes/api.php` com `middleware('permission:...')` e `throttle:api-write`
10. ☐ Execute `php artisan l5-swagger:generate`
11. ☐ Verifique em `/docs/api` se os endpoints aparecem
12. ☐ Escreva **testes** para o Service e ValueObjects utilizados

> 💡 O comando `make:domain` já registra a tag Swagger e o binding automaticamente.

---

> **Food99 Integration Hub v3** — Laravel 13 / PHP 8.5+
> Arquitetura idealizada por **Rafael Rozgrin** <rrozgrin@gmail.com>
## 6. Model

Domain Events permitem que domínios se comuniquem sem acoplamento direto. Use o EventDispatcher nativo do Laravel.

### Criando um Domain Event

```php
// app/Events/Domain/Venda/VendaRealizadaEvent.php
namespace App\Events\Domain\Venda;

use App\Events\Domain\DomainEvent;
use App\ValueObjects\Money;

class VendaRealizadaEvent extends DomainEvent
{
    public function __construct(
        public readonly int $vendaId,
        public readonly int $clienteId,
        public readonly Money $totalVenda,
        public readonly array $itens,
    ) {
        parent::__construct();
    }
}
```

### Disparando Events no Service

```php
use App\Events\Domain\Venda\VendaRealizadaEvent;

class VendaService
{
    use WithTransaction;

    public function finalizar(int $pedidoId): object
    {
        return $this->transaction(function () use ($pedidoId) {
            $venda = $this->repository->create([/* ... */]);

            // Dispara DENTRO da transaction — rollback cancela o evento
            event(new VendaRealizadaEvent(
                vendaId: $venda->id,
                clienteId: $venda->cliente_id,
                totalVenda: $venda->total,
                itens: $venda->itens->toArray(),
            ));

            return $venda;
        });
    }
}
```

### Registrando Listeners

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    VendaRealizadaEvent::class => [
        AtualizarEstoqueListener::class,
        GerarComissaoListener::class,
        NotificarClienteListener::class,
    ],
];
```

---

## 7. Padrão de Resposta da API

> O driver padrão é **Redis**. Nunca use `database` como cache em produção com 4k+ clientes.

### TTLs por domínio (config/cache.php)

```php
'ttl' => [
    'caixa'          => 60,      //  1 min  — dados em tempo real
    'relatorios'     => 300,     //  5 min  — relatórios frequentes
    'clientes'       => 1800,    // 30 min  — cadastros
    'produtos'       => 3600,    //  1 hora — catálogo
    'precos'         => 900,     // 15 min  — preços (sensível a promoções)
    'configuracoes'  => 86400,   // 24 hrs  — configurações do sistema
],
```

### CacheService — API padronizada de cache

```php
use App\Services\Cache\CacheService;

class ProdutoService
{
    public function __construct(
        private readonly ProdutoRepositoryInterface $repository,
        private readonly CacheService $cache,
    ) {}

    public function buscarPorId(int $id): object
    {
        return $this->cache->remember(
            key: "produto:{$id}",
            callback: fn() => $this->repository->find($id),
            ttl: config('cache.ttl.produtos'), // 3600s
        );
    }

    public function criar(array $data): object
    {
        $produto = $this->repository->create($data);

        // Invalida o cache da listagem de produtos
        $this->cache->forgetByTag('produtos');

        return $produto;
    }
}
```

### CacheableRepository — Cache automático no Repository

```php
use App\Repository\Traits\CacheableRepository;

class ProdutoEloquentRepository extends EloquentRepository
    implements ProdutoRepositoryInterface
{
    use CacheableRepository;

    // TTL em segundos para este repositório
    protected int $cacheTtl = 3600;

    // Tags Redis para invalidação em grupo
    protected array $cacheTags = ['produtos', 'catalogo'];

    public function __construct(Produto $model)
    {
        parent::__construct($model);
    }
}
// Agora find(), findAll(), paginate() são automaticamente cacheados.
// create(), update(), delete() invalidam o cache automaticamente.

// Para desabilitar cache em uma query específica:
$produtos = $this->repository->withoutCache()->findAll();
```

---

## 8. Repository — CRUD Base

A trait `WithTransaction` padroniza o uso de transações nos Services.

### Uso básico

```php
use App\Services\Traits\WithTransaction;

class VendaService
{
    use WithTransaction;

    public function criar(array $dados): object
    {
        // Tudo dentro do closure é atômico
        return $this->transaction(function () use ($dados) {
            $venda   = $this->vendaRepo->create($dados);
            $this->estoqueRepo->baixar($dados['itens']);
            $this->financeiroRepo->lancarCredito($venda);
            event(new VendaRealizadaEvent($venda->id, ...));
            return $venda;
        });
        // Em qualquer falha → rollback automático de TUDO
    }
}
```

### Retry automático para deadlocks

```php
// Tenta até 3 vezes em caso de deadlock MySQL (InnoDB)
return $this->transactionWithRetry(
    callback: fn() => $this->repository->create($dados),
    attempts: 3,
);
```

### Regra de uso

| Situação                                  | Usar transação? |
|-------------------------------------------|-----------------|
| Operação em 1 tabela simples              | ❌ Opcional      |
| Venda (venda + estoque + financeiro)      | ✅ Obrigatório   |
| Fechamento de caixa                       | ✅ Obrigatório   |
| Locação (reserva + contrato + cobrança)   | ✅ Obrigatório   |
| Simples `find()` ou `paginate()`          | ❌ Desnecessário |

---

## 9. DataTables — Suporte Opt-in

### Rate Limiting (configurado em AppServiceProvider::boot)

| Limiter         | Limite    | Quando usar                              |
|-----------------|-----------|------------------------------------------|
| `api-read`      | 100/min   | Consultas, listagens                     |
| `api-write`     | 30/min    | Criações, atualizações, exclusões        |
| `api-reports`   | 5/min     | Geração de relatórios                    |

```php
// Aplicando nas rotas
Route::get('/relatorios/financeiro', [RelatorioController::class, 'financeiro'])
    ->middleware(['permission:relatorio.financeiro', 'throttle:api-reports']);

Route::post('/produtos', [ProdutoController::class, 'store'])
    ->middleware('throttle:api-write');
```

### Queues com Redis

Para operações pesadas ou demoradas (relatórios, e-mails, NF-e), use Jobs assíncronos:

```php
// Definindo o Job
class GerarRelatorioFinanceiroJob implements ShouldQueue
{
    public $queue = 'relatorios';    // Fila dedicada
    public $timeout = 600;           // 10 minutos
    public $tries = 1;               // Apenas 1 tentativa — relatórios não repetem

    public function handle(RelatorioService $service): void
    {
        $service->gerar($this->params);
    }
}

// Despachando do Service
GerarRelatorioFinanceiroJob::dispatch($params)->onQueue('relatorios');
```

### Filas disponíveis

| Fila           | Descrição                    | Timeout | Tries |
|----------------|------------------------------|---------|-------|
| `default`      | Operações gerais             | 60s     | 3     |
| `relatorios`   | Geração de relatórios PDF    | 600s    | 1     |
| `emails`       | Envio de e-mail/notificações | 60s     | 3     |
| `fiscal`       | NF-e, NFC-e, SPED            | 300s    | 2     |

### Iniciando workers

```bash
# Worker de relatórios (processo separado)
php artisan queue:work redis --queue=relatorios --timeout=600

# Worker de e-mails
php artisan queue:work redis --queue=emails

# Worker fiscal
php artisan queue:work redis --queue=fiscal --timeout=300
```

---

## 10. Service — Lógica de Negócio

Casts fazem a ponte automática entre o banco de dados (strings/decimais) e os Value Objects. Ficam em `app/Casts/`.

### Regra de ouro das colunas monetárias

```sql
-- Definição correta no banco de dados
ALTER TABLE webc_produto ADD COLUMN preco DECIMAL(15,8) NOT NULL DEFAULT '0.00000000';
--                                        ^^^^^^^^^^^^^
--                          15 dígitos totais, 8 decimais
--                          Suporta até R$ 9.999.999,99999999
```

### Usando Casts nos Models

```php
use App\Casts\MoneyCast;
use App\Casts\PercentageCast;
use App\Casts\QuantityCast;
use App\Casts\CpfCast;
use App\Casts\CnpjCast;
use App\Casts\PeriodCast;

class Produto extends Model
{
    protected $table = 'webc_produto';

    protected function casts(): array
    {
        return [
            // Valores monetários (DECIMAL 15,8 no banco)
            'preco'              => MoneyCast::class,
            'custo'              => MoneyCast::class,
            'preco_promocional'  => MoneyCast::class,

            // Percentuais fiscais
            'desconto_max'       => PercentageCast::class,
            'aliquota_icms'      => PercentageCast::class,
            'comissao'           => PercentageCast::class,

            // Quantidades
            'estoque_minimo'     => QuantityCast::class,

            // Documentos — armazenados como apenas dígitos no banco
            'cpf_responsavel'    => CpfCast::class,
            'cnpj_fabricante'    => CnpjCast::class,
        ];
    }
}
```

### Como funciona na prática

```php
// Ao LER do banco — cast automático para VO
$produto = Produto::find(1);
$produto->preco;          // instância de Money, não string!
$produto->aliquota_icms;  // instância de Percentage

// Operações direto no VO
$total = $produto->preco->mul('3');    // Money: 3 unidades

// Ao SALVAR — cast automático para string do banco
$produto->preco = Money::of('59.90');  // ou string: '59.90'
$produto->save();                       // salva '59.90000000' no banco
```

### QuantityCast com precisão customizada

```php
// Precisão padrão: 8 casas decimais
'quantidade' => QuantityCast::class,

// Precisão customizada (ex: 3 casas para peso em kg)
'peso_kg'    => QuantityCast::class . ':3',
```

---

## 11. DTO — Data Transfer Object

| Aspecto               | apidefault (Laravel 8)              | Food99 Integration Hub (Laravel 13)           |
|------------------------|--------------------------------------|------------------------------------------------|
| PHP                    | 7.3 / 8.0                           | 8.5+                                            |
| CORS                   | `fruitcake/laravel-cors` (abandonado)| Nativo em `bootstrap/app.php`                   |
| JWT                    | `tymon/jwt-auth` (sem manutenção)    | `php-open-source-saver/jwt-auth` v2             |
| DTO Serialização       | JMS Serializer (incompatível PHP 8.5)| Readonly classes + RequestBodyConverter          |
| DataTables             | Obrigatório em todo Repository       | **Opt-in** via trait HasDataTables              |
| Documentação API       | Nenhuma                              | Swagger/OpenAPI + Scalar UI                     |
| Tipagem                | Parcial                              | Strict types + constructor promotion + readonly |
| Exception Handling     | Básico                               | Customizado com throttle + alertas              |
| Resposta               | SendResponse simples                 | ResponseApi + ResponseApiDev por ambiente       |
| Middleware             | `app/Http/Kernel.php`                | `bootstrap/app.php`                             |
| Scaffolding            | Comandos make básicos                | make:domain com hierarquia + Swagger + Binding  |
| Casts                  | Property `$casts`                    | Método `casts(): array`                         |
| Valores monetários     | `float` nativo — impreciso           | `Money` VO com BCMath — precisão de 8 decimais  |
| Arredondamento         | Nenhum padrão                        | ABNT NBR 5891 via `RoundingService`             |
| Cache                  | Driver `database` (lento)            | Redis com tags por domínio + TTL configurável   |
| RBAC                   | Nenhum controle de acesso            | Roles + Permissions + JWT claims + Redis cache  |
| Audit Trail            | Nenhuma rastreabilidade              | `Auditable` trait → `webc_auditoria`            |
| Transações             | Manual (`DB::transaction()` avulso)  | `WithTransaction` trait nos Services            |
| Domain Events          | Acoplamento direto entre domínios    | `DomainEvent` abstrato + Laravel EventDispatcher|
| Filas                  | Driver `database` (bloqueante)       | Redis com conexões especializadas por tipo      |
| Rate Limiting          | Nenhum                               | 3 níveis: leitura/escrita/relatórios            |
| `@author`              | Não                                  | Sim — Rafael Rozgrin em todos os arquivos       |

---

## 12. Controller

> Toda aritmética fiscal usa `BCMath`. **Nunca use `float`** em cálculos de impostos.

### FiscalCalculatorService — Operações

```php
use App\Services\Fiscal\FiscalCalculatorService;
use App\ValueObjects\Money;
use App\ValueObjects\Percentage;
use App\ValueObjects\Quantity;

class VendaService
{
    public function __construct(
        private readonly FiscalCalculatorService $fiscal,
    ) {}

    public function calcularItemVenda(array $item): Money
    {
        $preco      = Money::of($item['preco_unitario']);
        $desconto   = Percentage::of($item['desconto']);
        $quantidade = Quantity::of($item['quantidade']);
        $icms       = Percentage::of($item['aliquota_icms']);

        // Aplica desconto
        $precoComDesconto = $this->fiscal->applyDiscount(
            price: $preco,
            discount: $desconto,
        );

        // Calcula total do item
        $totalItem = $this->fiscal->calculateTotal(
            quantity: $quantidade,
            unitPrice: $precoComDesconto,
        );

        // Calcula ICMS
        $valorIcms = $this->fiscal->calculateIcmsInternal(
            baseCalculo: $totalItem,
            aliquota: $icms,
        );

        return $totalItem;
    }

    public function calcularTotalPedido(array $itens): Money
    {
        // Soma TODOS os itens com BCMath (sem acumulação de erros)
        return $this->fiscal->sumItems(
            items: array_map(fn($i) => $this->calcularItemVenda($i), $itens),
        );
    }

    public function ratearFrete(Money $frete, array $totaisItens): array
    {
        // Rateio proporcional — garante que a soma dos rateios = frete original
        return $this->fiscal->proRata(
            total: $frete,
            weights: $totaisItens,
        );
    }
}
```

### RoundingService — Modos de Arredondamento

```php
use App\Services\Fiscal\RoundingService;

$rounder = new RoundingService();

// HALF_UP (padrão ABNT NBR 5891) — obrigatório para NF-e
$rounder->halfUp('2.345', 2);     // '2.35'
$rounder->halfUp('2.344', 2);     // '2.34'

// HALF_DOWN
$rounder->halfDown('2.345', 2);   // '2.34'

// HALF_EVEN (Banker's rounding) — para contabilidade
$rounder->halfEven('2.345', 2);   // '2.34'
$rounder->halfEven('2.355', 2);   // '2.36'

// TRUNCATE — simplesmente corta sem arredondar
$rounder->truncate('2.349', 2);   // '2.34'
```

---

## 13. Exceções

DataTables server-side é **OPCIONAL**. Nem todo repository precisa. Apenas adicione quando o domínio precisar de DataTables no frontend.

### Quando USAR DataTables:
- ✅ Tabelas com muitos registros que precisam de paginação/busca server-side no frontend
- ✅ Quando o frontend usa jQuery DataTables

### Quando NÃO usar DataTables:
- ❌ APIs puras (sem frontend DataTables)
- ❌ Repositórios de configuração, lookup, cache

### Como adicionar DataTables

1. Implemente `DataTablesInterface` na interface do repository
2. Use a trait `HasDataTables` na implementação
3. Configure `$searchableColumns` e `$orderableColumns`

### Exemplo Completo — Repository COM DataTables

```php
<?php

namespace App\Repository\Eloquent\Models\Produto;

use App\Models\Produto;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Traits\HasDataTables;
use App\Repository\Contracts\DataTablesInterface;
use App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface;

/**
 * Repositório de Produto com suporte a DataTables server-side.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ProdutoEloquentRepository extends EloquentRepository
    implements ProdutoRepositoryInterface, DataTablesInterface
{
    use HasDataTables;

    // Colunas pesquisáveis (busca global — OR entre elas)
    protected array $searchableColumns = ['nome', 'sku', 'descricao'];

    // Colunas ordenáveis — índice corresponde à posição da coluna no frontend
    protected array $orderableColumns = ['id', 'nome', 'preco', 'data_criacao'];

    // Ordenação padrão
    protected string $defaultOrderColumn = 'data_criacao';
    protected string $defaultOrderDirection = 'desc';

    public function __construct(Produto $model)
    {
        parent::__construct($model);
    }

    /**
     * Personaliza a saída para o DataTables.
     * Opcional — o padrão da trait retorna toArray() do Model.
     */
    protected function transformForDataTables(mixed $item): array
    {
        return [
            'id'    => $item->id,
            'nome'  => $item->nome,
            // $item->preco é um Money VO — use round(2)->toFloat() para display
            'preco' => 'R$ ' . number_format($item->preco->round(2)->toFloat(), 2, ',', '.'),
            'ativo' => $item->ativo === 'S' ? 'Ativo' : 'Inativo',
        ];
    }
}
```

### Resposta do DataTables

```json
{
    "draw": 1,
    "recordsTotal": 523,
    "recordsFiltered": 42,
    "data": [
        { "id": 1, "nome": "Produto A", "preco": "R$ 29,90", "ativo": "Ativo" },
        { "id": 2, "nome": "Produto B", "preco": "R$ 149,00", "ativo": "Inativo" }
    ]
}
```

### Propriedades configuráveis da trait HasDataTables

| Propriedade              | Tipo     | Default   | Descrição                              |
|--------------------------|----------|-----------|----------------------------------------|
| `$searchableColumns`     | `array`  | `[]`      | Colunas para busca global (OR)         |
| `$orderableColumns`      | `array`  | `[]`      | Colunas ordenáveis (por índice)        |
| `$defaultOrderColumn`    | `string` | `'id'`    | Coluna padrão de ordenação             |
| `$defaultOrderDirection` | `string` | `'desc'`  | Direção padrão (asc/desc)              |

### Usando no Controller/Service

```php
// No Service
public function dataTables(Request $request): array
{
    return $this->repository->dataTables(request: $request);
}

// No Controller
public function dataTables(Request $request): JsonResponse
{
    return response()->json($this->service->dataTables(request: $request));
}
```

---

## 14. Autenticação JWT

```
app/
├── Casts/                           ← Eloquent Casts para Value Objects 🆕
│   ├── MoneyCast.php                ← DECIMAL(15,8) → Money VO
│   ├── PercentageCast.php           ← decimal → Percentage VO
│   ├── QuantityCast.php             ← decimal → Quantity VO
│   ├── CpfCast.php                  ← string → Cpf VO
│   ├── CnpjCast.php                 ← string → Cnpj VO
│   └── PeriodCast.php               ← string → Period VO
├── Console/Commands/                ← Comandos Artisan customizados
│   ├── Concerns/                    ← Traits dos comandos (ParsesNameArgument)
│   ├── MakeDomainCommand.php        ← make:domain
│   ├── MakeRepositoryCommand.php    ← make:repository
│   ├── MakeServiceCommand.php       ← make:service
│   └── MakeDtoCommand.php           ← make:dto
├── DTO/                             ← Data Transfer Objects (readonly classes)
│   └── {Domínio}/                   ← Organizados por domínio
├── Events/Domain/                   ← Domain Events 🆕
│   ├── DomainEvent.php              ← Classe abstrata base
│   └── {Domínio}/                   ← Events por domínio (ex: Venda/)
├── Exceptions/
│   ├── AlertaException.php          ← Exceção com nível de severidade
│   ├── ApiException.php   ← Exceção de regra de negócio
│   └── HandlerExceptionCritical.php ← Trait para exceções críticas
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php           ← Base com trait SendResponse
│   │   ├── AuthController.php       ← JWT (login, me, refresh)
│   │   └── {Domínio}/               ← Controllers por domínio
│   └── Middleware/
│       ├── ApiJwtMiddleware.php     ← Validação de token JWT
│       └── CheckPermissionMiddleware.php ← Verificação RBAC 🆕
├── Jobs/                            ← Queue Jobs assíncronos 🆕
│   └── {Domínio}/                   ← Jobs por domínio (ex: Relatorio/)
├── Models/                          ← Models Eloquent por domínio
│   ├── Permission.php               ← RBAC — Permissão 🆕
│   ├── Role.php                     ← RBAC — Papel/Função 🆕
│   └── Traits/
│       └── Auditable.php            ← Audit Trail automático 🆕
├── OpenApi/
│   └── OpenApiSpec.php              ← Definição global do Swagger
├── Repository/
│   ├── Contracts/
│   │   ├── RepositoryInterface.php  ← Contrato CRUD base
│   │   ├── DataTablesInterface.php  ← Contrato DataTables (opt-in)
│   │   └── Models/{Domínio}/        ← Interfaces por domínio
│   ├── Eloquent/
│   │   ├── AbstractEloquentRepository.php ← CRUD genérico
│   │   ├── EloquentRepository.php         ← Configuração de conexão
│   │   └── Models/{Domínio}/              ← Repositórios por domínio
│   └── Traits/
│       ├── HasDataTables.php        ← Trait opt-in para DataTables server-side
│       └── CacheableRepository.php ← Cache automático de leitura 🆕
└── Services/
    ├── Auth/
    │   └── UsuarioLogadoService.php ← Dados do usuário autenticado
    ├── Cache/
    │   └── CacheService.php         ← Cache Redis com tags por domínio 🆕
    ├── Fiscal/
    │   ├── RoundingService.php      ← Arredondamento ABNT NBR 5891 🆕
    │   └── FiscalCalculatorService.php ← Cálculos fiscais BCMath 🆕
    ├── Extensions/
    │   ├── BindsRepositorios.php          ← Registro de bindings IoC
    │   ├── RequestBodyConverter.php       ← Conversor JSON→DTO
    │   └── RequestBodyConverterInterface.php ← Interface marcadora
    ├── ResponseApi/
    │   ├── ResponseApi.php          ← Resposta JSON (produção)
    │   └── ResponseApiDev.php       ← Resposta JSON com debug (dev)
    ├── Traits/
    │   ├── SendResponse.php         ← Trait de resposta padronizada
    │   └── WithTransaction.php      ← Gerenciamento de transações DB 🆕
    ├── ValueObjects/                ← Value Objects fiscais 🆕
    │   ├── Money.php                ← Aritmética monetária com BCMath
    │   ├── Percentage.php           ← Taxas fiscais (ICMS, ISS, PIS...)
    │   ├── Quantity.php             ← Quantidades com casas decimais
    │   ├── Cpf.php                  ← CPF validado com dígitos verificadores
    │   ├── Cnpj.php                 ← CNPJ validado com dígitos verificadores
    │   ├── Period.php               ← Período fiscal (início/fim)
    │   └── RoundingMode.php         ← Enum: HALF_UP, HALF_DOWN, HALF_EVEN, TRUNCATE
    └── {Domínio}/                   ← Services com lógica por domínio
```

---

## 15. RBAC — Controle de Acesso

Controllers são **enxutos**. Recebem a request, delegam ao Service e retornam usando `$this->send()`. Incluem anotações Swagger com PHP Attributes.

### Exemplo Completo

```php
<?php

namespace App\Http\Controllers\Produto;

use App\Http\Controllers\Controller;
use App\Services\Produto\ProdutoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

/**
 * Controller de Produto.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ProdutoController extends Controller
{
    public function __construct(
        private readonly ProdutoService $service,
    ) {}

    #[OA\Get(
        path: '/produtos',
        summary: 'Listar Produtos',
        tags: ['Produto'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista retornada com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaPadrao'),
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function index(): JsonResponse
    {
        return response()->json(
            data: $this->send(conteudo: $this->service->listar()),
        );
    }

    #[OA\Get(
        path: '/produtos/{id}',
        summary: 'Buscar Produto por ID',
        tags: ['Produto'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Registro encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/RespostaPadrao'),
            ),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(
            data: $this->send(conteudo: $this->service->buscarPorId(id: $id)),
        );
    }

    #[OA\Post(
        path: '/produtos',
        summary: 'Criar Produto',
        tags: ['Produto'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProdutoDTO'),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Criado com sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ],
    )]
    public function store(): JsonResponse
    {
        return response()->json(
            data: $this->send(
                conteudo: $this->service->criar(),
                code: Response::HTTP_CREATED,
                msg: 'Produto criado com sucesso.',
            ),
            status: Response::HTTP_CREATED,
        );
    }

    #[OA\Put(
        path: '/produtos/{id}',
        summary: 'Atualizar Produto',
        tags: ['Produto'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProdutoDTO'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Atualizado com sucesso'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function update(int $id): JsonResponse
    {
        return response()->json(
            data: $this->send(
                conteudo: $this->service->atualizar(id: $id),
                msg: 'Produto atualizado com sucesso.',
            ),
        );
    }

    #[OA\Delete(
        path: '/produtos/{id}',
        summary: 'Remover Produto',
        tags: ['Produto'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Removido com sucesso'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function destroy(int $id): JsonResponse
    {
        return response()->json(
            data: $this->send(
                conteudo: $this->service->remover(id: $id),
                msg: 'Produto removido com sucesso.',
            ),
        );
    }
}
```

---

## 16. Rotas da API

> **⚠️ Regra crítica:** NUNCA use `float` para valores monetários. Sempre use `Money` VO com BCMath.

Value Objects (VOs) são classes imutáveis (`readonly`) que encapsulam um valor com suas regras de validação e operações. Vivem em `app/ValueObjects/`.

### Por que BCMath e não float?

```php
// ❌ ERRADO — float acumula erros de ponto flutuante
$total = 0.1 + 0.2; // = 0.30000000000000004 em PHP

// ✅ CORRETO — BCMath é exato
$total = bcadd('0.1', '0.2', 8); // = '0.20000000'
```

### Money — Aritmética Monetária

```php
use App\ValueObjects\Money;

// Criação
$preco     = Money::of('29.90');           // de string (preferido)
$desconto  = Money::of('5.00');
$imposto   = Money::of('0.00000000');

// Aritmética — todos os métodos retornam nova instância (imutável)
$subtotal  = $preco->sub($desconto);       // R$ 24.90
$comJuros  = $preco->mul('1.025');         // multiplica por 1.025 (2.5% de juros)
$dividido  = $preco->div('3');             // divisão exata com BCMath
$somados   = $preco->add($desconto);       // soma

// Arredondamento — sempre ao final, nunca no meio do cálculo
$final     = $subtotal->round(2);          // arredonda para 2 casas (HALF_UP)

// Comparação
$preco->equals($desconto);                 // false
$preco->greaterThan($desconto);            // true
$preco->isZero();                          // false
$preco->isPositive();                      // true
$preco->isNegative();                      // false

// Saída
$preco->toString();   // '29.90000000' — use para salvar no banco
$preco->toFloat();    // 29.9 — use APENAS para display/formatação
```

### Percentage — Taxas Fiscais

```php
use App\ValueObjects\Percentage;
use App\ValueObjects\Money;

$aliquotaIcms = Percentage::of('12.00');   // 12%
$aliquotaPis  = Percentage::of('0.65');    // 0.65%

// Aplicar sobre um valor
$baseCalculo  = Money::of('1000.00');
$valorIcms    = $aliquotaIcms->applyTo($baseCalculo); // Money de R$ 120.00

$aliquotaIcms->toDecimal();   // '0.12000000' — fração decimal
$aliquotaIcms->toString();    // '12.00000000'
$aliquotaIcms->toFloat();     // 12.0 — apenas display
```

### Quantity — Quantidades

```php
use App\ValueObjects\Quantity;
use App\ValueObjects\Money;

$quantidade   = Quantity::of('2.500');           // 2,500 kg
$precoUnitario = Money::of('15.80');

// Calcular total: quantidade × preço unitário
$valorTotal = $quantidade->times($precoUnitario); // Money de R$ 39.50

$quantidade->toString();  // '2.50000000'
$quantidade->toFloat();   // 2.5
```

### Cpf / Cnpj — Documentos Fiscais

```php
use App\ValueObjects\Cpf;
use App\ValueObjects\Cnpj;

// Aceita com ou sem formatação — valida dígitos verificadores
$cpf  = Cpf::of('123.456.789-09');   // ou '12345678909'
$cnpj = Cnpj::of('11.222.333/0001-81');

// Acesso
$cpf->digits();     // '12345678909' — apenas dígitos (para gravar no banco)
$cpf->formatted();  // '123.456.789-09' — formatado
$cpf->isValid();    // true

// Cpf inválido lança ApiException:
Cpf::of('111.111.111-11'); // throw ApiException(422)
```

### Period — Períodos Fiscais

```php
use App\ValueObjects\Period;

$competencia = Period::of('2025-01-01', '2025-01-31');

// Verificações
$competencia->contains(new DateTime('2025-01-15'));  // true
$competencia->contains(new DateTime('2025-02-01'));  // false

$outroPeriodo = Period::of('2025-01-20', '2025-02-28');
$competencia->overlaps($outroPeriodo);  // true — períodos se sobrepõem

$competencia->days();   // 31 (diferença em dias)
$competencia->start();  // DateTimeImmutable
$competencia->end();    // DateTimeImmutable
$competencia->toString(); // '2025-01-01/2025-01-31' (para banco)
```

---

## 17. Swagger / OpenAPI

Models representam tabelas do banco. Tabelas legadas (prefixo `webc_`) usam timestamps em português. **Colunas monetárias SEMPRE usam `MoneyCast::class` — nunca `'decimal:2'`.**

### Exemplo — Tabela Legada com Value Objects

```php
<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\PercentageCast;
use App\Casts\QuantityCast;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Eloquent para Produto.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class Produto extends Model
{
    use Auditable;

    protected $table = 'webc_produto';

    // Timestamps legados em português
    const CREATED_AT = 'data_criacao';
    const UPDATED_AT = 'data_alteracao';

    protected $fillable = [
        'nome',
        'descricao',
        'preco',
        'aliquota_icms',
        'quantidade',
        'ativo',
        'categoria_id',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'preco'         => MoneyCast::class,      // DECIMAL(15,8) no banco
            'aliquota_icms' => PercentageCast::class,  // percentual fiscal
            'quantidade'    => QuantityCast::class,    // estoque
            'data_criacao'  => 'datetime',
        ];
    }
}
```

### Convenções de Models

- Tabelas legadas com `webc_` → use `protected $table` + `CREATED_AT`/`UPDATED_AT` customizados
- Use `$fillable` — nunca `$guarded = []`
- Casts modernos via método `casts()` (não property `$casts`)
- **Colunas monetárias** → `MoneyCast::class` (nunca `'decimal:2'` para valores fiscais)
- **Colunas de percentual** → `PercentageCast::class`
- **Colunas de CPF/CNPJ** → `CpfCast::class` / `CnpjCast::class`
- **Auditoria** → adicione `use Auditable` quando o domínio precisar de rastreabilidade
- Relacionamentos com return type

---

## 18. Cache com Redis

Autenticação via JWT usando `php-open-source-saver/jwt-auth` (fork mantido do `tymon/jwt-auth`).

### Endpoints

| Endpoint           | Método | Autenticação | Descrição                |
|--------------------|--------|--------------|--------------------------|
| `/api/v1/login`    | POST   | Pública      | Login — retorna JWT      |
| `/api/v1/me`       | GET    | JWT          | Dados do usuário logado  |
| `/api/v1/refresh`  | POST   | JWT          | Renova o token           |

### Header de autenticação

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Middleware `jwt.verify`

Registrado em `bootstrap/app.php`. Valida o token em todas as rotas protegidas.

Erros retornados:
- **Token inválido** → `401` com `conteudo: 'token-error'`
- **Token expirado** → `401` com `conteudo: 'token-expirado'`
- **Token ausente** → `401` com mensagem

> O payload do token inclui as roles do usuário em `getJWTCustomClaims()`, mas as permissões detalhadas são buscadas via cache Redis para evitar token bloat. Ver seção 15 (RBAC).

---

## 19. Calculadora Fiscal

O sistema usa RBAC (Role-Based Access Control) integrado ao JWT.

### Estrutura de permissões

Formato: `{modulo}.{acao}` — exemplos:

```
clientes.ver         clientes.criar       clientes.editar      clientes.excluir
produtos.ver         produtos.criar       produtos.editar      produtos.excluir
vendas.ver           vendas.criar         vendas.cancelar
caixa.abrir          caixa.fechar         caixa.ver
relatorio.financeiro relatorio.fiscal
admin.tudo           ← permissão mestre (bypass de todas as verificações)
```

### Aplicando nas Rotas

```php
// routes/api.php
Route::group(['middleware' => ['jwt.verify']], function (): void {

    // Qualquer usuário autenticado pode ver
    Route::get('/produtos', [ProdutoController::class, 'index']);

    // Apenas quem tem permissão específica pode criar/editar
    Route::post('/produtos', [ProdutoController::class, 'store'])
        ->middleware('permission:produtos.criar');

    Route::put('/produtos/{id}', [ProdutoController::class, 'update'])
        ->middleware('permission:produtos.editar');

    Route::delete('/produtos/{id}', [ProdutoController::class, 'destroy'])
        ->middleware('permission:produtos.excluir');

    // Relatórios — apenas financeiro e gerente
    Route::get('/relatorios/financeiro', [RelatorioController::class, 'financeiro'])
        ->middleware('permission:relatorio.financeiro');
});
```

### Verificando permissão no Service

```php
use App\Services\Auth\UsuarioLogadoService;

class VendaService
{
    public function cancelar(int $vendaId): bool
    {
        $usuario = $this->usuarioLogado->getUser();

        if (!$usuario->hasPermission('vendas.cancelar')) {
            throw new ApiException(
                msg: 'Você não tem permissão para cancelar vendas.',
                code: Response::HTTP_FORBIDDEN,
            );
        }

        return $this->repository->cancelar($vendaId);
    }
}
```

### Roles disponíveis (default)

| Role        | Descrição                                    |
|-------------|----------------------------------------------|
| `admin`     | Acesso total (`admin.tudo`)                  |
| `gerente`   | Relatórios + vendas + cadastros              |
| `vendedor`  | Vendas + visualização de produtos            |
| `caixa`     | Operação de caixa                            |
| `tecnico`   | Assistência técnica                          |
| `financeiro`| Relatórios financeiros + contas              |

### Cache de permissões

As permissões do usuário são cacheadas no Redis por **15 minutos** para evitar queries a cada request. Após alterar roles:

```php
$usuario->invalidatePermissionsCache();
```

---

## 20. Domain Events

### URLs disponíveis

| URL                  | Descrição                                     |
|----------------------|-----------------------------------------------|
| `/docs/api`          | 🚀 **Scalar** — UI moderna, layout 3 colunas  |
| `/api/documentation` | 📋 Swagger UI clássico                        |
| `/docs/openapi.json` | 📄 JSON da especificação OpenAPI              |

### Regenerar documentação

```bash
php artisan l5-swagger:generate
```

### Schemas reutilizáveis (definidos em `OpenApiSpec.php`)

| Schema                  | Descrição                                       |
|-------------------------|-------------------------------------------------|
| `RespostaPadrao`        | Resposta de sucesso (conteudo, msg, code)        |
| `RespostaErro`          | Resposta de erro em produção                     |
| `RespostaErroDetalhado` | Resposta de erro com debug (dev)                 |
| `TokenJWT`              | Resposta do login (access_token, token_type)     |

### Anotações — SEMPRE usar PHP 8+ Attributes

```php
use OpenApi\Attributes as OA;

// ✅ CORRETO — PHP 8+ Attributes
#[OA\Get(
    path: '/produtos',
    tags: ['Produto'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Sucesso',
            content: new OA\JsonContent(ref: '#/components/schemas/RespostaPadrao')),
    ],
)]

// ❌ ERRADO — NÃO usar docblock annotations
// @OA\Get(path="/produtos")
```

### Tags

As tags são registradas automaticamente pelo `make:domain` em `OpenApiSpec.php`. Para registrar manualmente:

```php
// Em app/OpenApi/OpenApiSpec.php, adicione antes de "class OpenApiSpec {}":
#[OA\Tag(
    name: 'Produto',
    description: 'Operações de Produto',
)]
```

---


> **Food99 Integration Hub v3** — Laravel 13 / PHP 8.5+
> Arquitetura idealizada por **Rafael Rozgrin** <rrozgrin@gmail.com>
## 21. Audit Trail

DTOs são `readonly class` que representam o corpo da request. Usam `RequestBodyConverterInterface` como marcador e incluem anotações Swagger.

### Exemplo Completo

```php
<?php

namespace App\DTO\Produto;

use App\Services\Extensions\RequestBodyConverterInterface;
use OpenApi\Attributes as OA;

/**
 * DTO para transporte de dados de Produto.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
#[OA\Schema(
    schema: 'ProdutoDTO',
    title: 'Produto',
    description: 'Dados de Produto para criação/atualização.',
    required: ['nome', 'preco'],
)]
readonly class ProdutoDTO implements RequestBodyConverterInterface
{
    public function __construct(
        #[OA\Property(description: 'ID do registro', example: 1)]
        public ?int $id = null,

        #[OA\Property(description: 'Nome do produto', example: 'Produto A')]
        public string $nome = '',

        #[OA\Property(description: 'Preço unitário (string para precisão BCMath)', example: '29.90')]
        public string $preco = '0.00',

        #[OA\Property(description: 'Status ativo (S/N)', example: 'S')]
        public string $ativo = 'S',
    ) {}
}
```

### Conversão no Service

```php
$dto = $this->converter->deserialize(new ProdutoDTO());
// $dto->nome, $dto->preco, $dto->ativo agora contêm os valores do JSON
```

---

## 22. Gerenciamento de Transações

### Obrigatórias

| Regra                              | Exemplo                                                |
|------------------------------------|--------------------------------------------------------|
| Typed properties — sempre          | `private readonly ProdutoService $service`             |
| Constructor promotion — sempre     | `__construct(private readonly X $x)`                   |
| Named arguments — 2+ parâmetros   | `$this->send(conteudo: $data, code: 200)`              |
| Match expressions (não switch)     | `match($status) { 'A' => 'Ativo', default => '?' }`   |
| `config()` — nunca `env()` no app | `config('app.env')` ✅  `env('APP_ENV')` ❌              |
| PHPDoc em português               | `/** Busca um produto pelo ID. */`                     |
| `@author` obrigatório             | `@author Rafael Rozgrin <rrozgrin@gmail.com>` |
| Readonly para injeções            | `private readonly Service $service`                     |
| Trailing comma — sempre           | Em parâmetros, arrays, enums                            |
| Return types — sempre             | `public function listar(): mixed`                       |

### Proibidas

| ❌ NÃO Fazer                        | ✅ Fazer                                    |
|--------------------------------------|---------------------------------------------|
| `env('APP_ENV')` em código da app    | `config('app.env')`                         |
| `DB::table()` no Service            | Usar o Repository                            |
| Lógica de negócio no Controller      | Delegar para o Service                       |
| Lógica de negócio no Repository      | Retornar dados, lógica no Service            |
| Switch/case                          | Match expression                             |
| Docblock annotations `@OA\Get`       | PHP Attributes `#[OA\Get]`                   |
| `$guarded = []`                      | `$fillable = [...]`                          |
| Property `$casts`                    | Método `casts(): array`                      |
| `'decimal:2'` para valores fiscais   | `MoneyCast::class`                           |
| `float` em cálculos monetários       | `Money` VO com BCMath                        |

---

## 23. Rate Limiting e Filas

| Exceção                     | Quando Usar                                  | Código HTTP |
|-----------------------------|----------------------------------------------|-------------|
| `ApiException`    | Erros de regra de negócio                    | 400-499     |
| `AlertaException`           | Falhas de infraestrutura (DB, Redis, APIs)   | 500+        |

### ApiException

```php
throw new ApiException(
    msg: 'Produto não encontrado.',
    code: Response::HTTP_NOT_FOUND,    // 404
);

throw new ApiException(
    msg: 'Estoque insuficiente para esta operação.',
    code: Response::HTTP_UNPROCESSABLE_ENTITY,    // 422
);
```

### AlertaException

```php
throw new AlertaException(
    msg: 'Serviço de pagamento indisponível.',
    nivel: 'critical',    // Nível PSR-3: emergency, alert, critical, error, warning
);
```

### HandlerExceptionCritical

Trait que detecta exceções críticas e envia notificações com **throttle de 5 minutos** para evitar flood:

- `QueryException` com `SQLSTATE[HY000]` → banco de dados fora do ar
- `AlertaException` → alerta explícito do sistema

---


> **Food99 Integration Hub v3** — Laravel 13 / PHP 8.5+
> Arquitetura idealizada por **Rafael Rozgrin** <rrozgrin@gmail.com>
## 24. Comandos Artisan Customizados

Todas as respostas passam pela trait `SendResponse`, que retorna `ResponseApi` em produção e `ResponseApiDev` em desenvolvimento.

### ✅ Resposta de Sucesso (200)

```json
{
    "conteudo": { "id": 1, "nome": "Produto A" },
    "msg": "",
    "code": 200
}
```

### ❌ Resposta de Erro — Produção

```json
{
    "conteudo": null,
    "msg": "Produto não encontrado.",
    "code": 404
}
```

### 🐛 Resposta de Erro — Desenvolvimento (com debug)

```json
{
    "conteudo": null,
    "msg": "Produto não encontrado.",
    "code": 404,
    "file": "/app/Services/Produto/ProdutoService.php",
    "line": 42,
    "exception": "App\\Exceptions\\ApiException",
    "trace": "#0 ..."
}
```

### Uso no Controller

```php
// Sucesso simples
return response()->json(
    data: $this->send(conteudo: $dados),
);

// Com mensagem e código customizado
return response()->json(
    data: $this->send(
        conteudo: $criado,
        code: Response::HTTP_CREATED,
        msg: 'Produto criado com sucesso.',
    ),
    status: Response::HTTP_CREATED,
);
```

### Classes envolvidas

| Classe             | Localização                           | Descrição                          |
|--------------------|---------------------------------------|------------------------------------|
| `ResponseApi`      | `app/Services/ResponseApi/`           | Resposta para produção             |
| `ResponseApiDev`   | `app/Services/ResponseApi/`           | Resposta com debug (dev)           |
| `SendResponse`     | `app/Services/Traits/`                | Trait que escolhe a classe correta |

---

## 25. Hierarquia de Pastas nos Comandos

Todas as rotas ficam em `routes/api.php` sob o prefixo `/api/v1`.

```php
Route::group(['prefix' => 'v1'], function (): void {

    // === ROTAS PÚBLICAS ===
    Route::post('/login', [AuthController::class, 'login']);

    // === ROTAS PROTEGIDAS (JWT) ===
    Route::group(['middleware' => ['jwt.verify']], function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        // Domínio: Produtos
        Route::group(['prefix' => 'produtos'], function (): void {
            Route::get('/', [ProdutoController::class, 'index'])
                ->middleware('permission:produtos.ver');
            Route::get('/{id}', [ProdutoController::class, 'show'])
                ->middleware('permission:produtos.ver');
            Route::post('/', [ProdutoController::class, 'store'])
                ->middleware(['permission:produtos.criar', 'throttle:api-write']);
            Route::put('/{id}', [ProdutoController::class, 'update'])
                ->middleware(['permission:produtos.editar', 'throttle:api-write']);
            Route::delete('/{id}', [ProdutoController::class, 'destroy'])
                ->middleware('permission:produtos.excluir');
        });
    });
});
```

### Convenções de Rotas

- Prefixo do domínio em **plural minúsculo**: `produtos`, `categorias`, `usuarios`
- IDs em rotas: `{id}` — sempre
- Verbos HTTP:
  - `GET /` → listar
  - `GET /{id}` → buscar por ID
  - `POST /` → criar
  - `PUT /{id}` → atualizar
  - `DELETE /{id}` → remover
- Rotas de escrita sempre com `throttle:api-write`
- Rotas de leitura com `permission:{modulo}.ver`

---

## 26. Convenções de Código

O padrão Repository separa o acesso ao banco da lógica de negócio.

### Hierarquia de classes

```
RepositoryInterface (contrato CRUD)
    ↓
AbstractEloquentRepository (implementação base)
    ↓
EloquentRepository (configuração de conexão)
    ↓
{DomínioEloquentRepository} (implementação específica)
    + opcionalmente: use HasDataTables implements DataTablesInterface
```

### Métodos disponíveis no RepositoryInterface

| Método                  | Retorno    | Descrição                         |
|-------------------------|------------|-----------------------------------|
| `find($id)`             | `?object`  | Busca por ID                      |
| `findAll()`             | `?object`  | Retorna todos                     |
| `findBy($criteria, ...)` | `?object` | Busca por critérios dinâmicos     |
| `findOneBy($criteria)`  | `?object`  | Busca um único registro           |
| `paginate($perPage)`    | `mixed`    | Paginação                         |
| `create($data)`         | `object`   | Cria novo registro                |
| `update($data, $id)`    | `bool`     | Atualiza por ID                   |
| `delete($id)`           | `bool`     | Remove por ID                     |

### Exemplo Completo — Repository SEM DataTables

**Interface:**

```php
<?php

namespace App\Repository\Contracts\Models\Produto;

use App\Repository\Contracts\RepositoryInterface;

/**
 * Contrato para o repositório de Produto.
 *
 * @see RepositoryInterface — Contrato base CRUD
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
interface ProdutoRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca produtos por categoria.
     */
    public function findByCategoria(int $categoriaId): ?object;
}
```

**Implementação Eloquent:**

```php
<?php

namespace App\Repository\Eloquent\Models\Produto;

use App\Models\Produto;
use App\Repository\Eloquent\EloquentRepository;
use App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface;

/**
 * Implementação Eloquent do repositório de Produto.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ProdutoEloquentRepository extends EloquentRepository
    implements ProdutoRepositoryInterface
{
    public function __construct(Produto $model)
    {
        parent::__construct($model);
    }

    public function findByCategoria(int $categoriaId): ?object
    {
        return $this->model
            ->where('categoria_id', $categoriaId)
            ->get();
    }
}
```

**Binding (registrado automaticamente pelo `make:repository`):**

```php
// app/Services/Extensions/BindsRepositorios.php
$app->bind(
    \App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface::class,
    \App\Repository\Eloquent\Models\Produto\ProdutoEloquentRepository::class,
);
```

---

## 27. Checklist — Criando um Novo Domínio

Services são o **coração da aplicação**. TODA regra de negócio fica aqui. Recebem injeção do Repository, UsuarioLogadoService e RequestBodyConverter. Sempre usam a trait `WithTransaction` para operações de escrita.

### Exemplo Completo

```php
<?php

namespace App\Services\Produto;

use App\DTO\Produto\ProdutoDTO;
use App\Exceptions\ApiException;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Extensions\RequestBodyConverter;
use App\Services\Traits\WithTransaction;
use App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serviço de lógica de negócio para Produto.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class ProdutoService
{
    use WithTransaction;

    public function __construct(
        private readonly ProdutoRepositoryInterface $repository,
        private readonly UsuarioLogadoService $usuarioLogado,
        private readonly RequestBodyConverter $converter,
    ) {}

    /**
     * Lista produtos com paginação.
     */
    public function listar(int $perPage = 15): mixed
    {
        return $this->repository->paginate(perPage: $perPage);
    }

    /**
     * Busca um produto pelo ID.
     *
     * @throws ApiException Se não encontrado
     */
    public function buscarPorId(int $id): object
    {
        $produto = $this->repository->find(id: $id);

        if ($produto === null) {
            throw new ApiException(
                msg: 'Produto não encontrado.',
                code: Response::HTTP_NOT_FOUND,
            );
        }

        return $produto;
    }

    /**
     * Cria um novo produto a partir do body da request.
     */
    public function criar(): object
    {
        $dto = $this->converter->deserialize(new ProdutoDTO());

        return $this->transaction(function () use ($dto) {
            // 1. Regras de negócio pré-gravação
            // 2. Persistência
            $produto = $this->repository->create([
                'nome'       => $dto->nome,
                'preco'      => $dto->preco,
                'ativo'      => $dto->ativo,
                'criado_por' => $this->usuarioLogado->getId(),
            ]);
            // 3. Disparar evento (opcional)
            // event(new ProdutoCriadoEvent($produto->id));
            return $produto;
        });
    }

    /**
     * Atualiza um produto existente.
     *
     * @throws ApiException Se não encontrado
     */
    public function atualizar(int $id): bool
    {
        $this->buscarPorId(id: $id);
        $dto = $this->converter->deserialize(new ProdutoDTO());

        return $this->transaction(function () use ($dto, $id) {
            return $this->repository->update(
                data: [
                    'nome'  => $dto->nome,
                    'preco' => $dto->preco,
                    'ativo' => $dto->ativo,
                ],
                id: $id,
            );
        });
    }

    /**
     * Remove um produto pelo ID.
     *
     * @throws ApiException Se não encontrado
     */
    public function remover(int $id): bool
    {
        $this->buscarPorId(id: $id);
        return $this->repository->delete(id: $id);
    }
}
```

### Regras para Services

- **Uma classe por domínio** (ex: `ProdutoService`, `UsuarioService`)
- **Named arguments** obrigatórios para 2+ parâmetros
- **Exceções** de negócio são lançadas AQUI, nunca no Controller/Repository
- **Injeção de dependência** via constructor promotion com `readonly`
- **Sem acesso direto ao banco** — sempre via Repository
- **`use WithTransaction`** em todo Service com operações de escrita

---


> **Food99 Integration Hub v3** — Laravel 13 / PHP 8.5+
> Arquitetura idealizada por **Rafael Rozgrin** <rrozgrin@gmail.com>
