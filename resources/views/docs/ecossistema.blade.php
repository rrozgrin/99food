<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Generica — Guia do Desenvolvedor</title>
    <style>
        /* ===================================================================
         * RESET & BASE
         * =================================================================== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #1a1a2e;
            color: #e0e0e0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.7;
            font-size: 15px;
        }

        a { color: #64ffda; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ===================================================================
         * SIDEBAR
         * =================================================================== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 270px;
            height: 100vh;
            background: #16213e;
            padding: 24px 16px;
            overflow-y: auto;
            border-right: 1px solid #233554;
            z-index: 100;
        }
        .sidebar-logo {
            font-size: 18px;
            font-weight: 700;
            color: #ccd6f6;
            margin-bottom: 4px;
        }
        .sidebar-sub {
            font-size: 12px;
            color: #8892b0;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .sidebar-section {
            font-size: 11px;
            font-weight: 700;
            color: #64ffda;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 18px 0 8px 0;
        }
        .sidebar a {
            color: #a8b2d1;
            display: block;
            padding: 5px 8px;
            font-size: 13px;
            border-radius: 4px;
            transition: all 0.15s;
        }
        .sidebar a:hover {
            color: #64ffda;
            background: rgba(100, 255, 218, 0.05);
            text-decoration: none;
        }

        /* ===================================================================
         * CONTENT
         * =================================================================== */
        .content {
            margin-left: 270px;
            padding: 40px 50px 80px;
            max-width: 1050px;
        }

        h1 {
            font-size: 32px;
            color: #ccd6f6;
            margin-bottom: 6px;
        }
        h1 small {
            display: block;
            font-size: 14px;
            color: #8892b0;
            font-weight: 400;
            margin-top: 6px;
        }
        h2 {
            color: #ccd6f6;
            font-size: 22px;
            border-bottom: 1px solid #233554;
            padding-bottom: 10px;
            margin-top: 50px;
            margin-bottom: 20px;
        }
        h3 {
            color: #ccd6f6;
            font-size: 17px;
            margin-top: 30px;
            margin-bottom: 12px;
        }
        h4 {
            color: #a8b2d1;
            font-size: 15px;
            margin-top: 20px;
            margin-bottom: 8px;
        }
        p { margin-bottom: 14px; }

        /* ===================================================================
         * CODE BLOCKS
         * =================================================================== */
        pre {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 16px 20px;
            overflow-x: auto;
            margin: 14px 0 18px;
            font-size: 13px;
            line-height: 1.55;
        }
        code {
            font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
            font-size: 13px;
        }
        p code, li code, td code {
            background: #233554;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12.5px;
            color: #e6db74;
        }
        .cm  { color: #6a9955; }  /* comment */
        .kw  { color: #569cd6; }  /* keyword */
        .str { color: #ce9178; }  /* string */
        .tp  { color: #4ec9b0; }  /* type */
        .fn  { color: #dcdcaa; }  /* function */
        .var { color: #9cdcfe; }  /* variable */
        .at  { color: #c586c0; }  /* attribute / annotation */
        .num { color: #b5cea8; }  /* number */

        /* ===================================================================
         * TABLES
         * =================================================================== */
        table { width: 100%; border-collapse: collapse; margin: 14px 0 18px; }
        th {
            background: #233554;
            color: #ccd6f6;
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #1e2d3d;
            font-size: 13.5px;
        }
        tr:hover td { background: rgba(100, 255, 218, 0.03); }

        /* ===================================================================
         * BADGES
         * =================================================================== */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            font-family: monospace;
            letter-spacing: 0.5px;
        }
        .badge-get    { background: #1b4332; color: #52b788; }
        .badge-post   { background: #3d2c08; color: #f4a261; }
        .badge-put    { background: #2d1b69; color: #a78bfa; }
        .badge-delete { background: #4a1515; color: #ef4444; }

        /* ===================================================================
         * FLOW DIAGRAM
         * =================================================================== */
        .flow {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 16px 0 20px;
        }
        .flow-box {
            background: #233554;
            color: #ccd6f6;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            border: 1px solid #2d4a6f;
        }
        .flow-box.active {
            background: #0a3d2e;
            border-color: #64ffda;
            color: #64ffda;
        }
        .flow-arrow {
            color: #64ffda;
            font-size: 18px;
            font-weight: bold;
        }

        /* ===================================================================
         * CALLOUTS
         * =================================================================== */
        .callout {
            padding: 14px 18px;
            border-radius: 6px;
            margin: 14px 0 18px;
            border-left: 4px solid;
            font-size: 13.5px;
        }
        .callout-info    { background: #0d253f; border-color: #3b82f6; }
        .callout-warning { background: #2d2305; border-color: #f59e0b; }
        .callout-success { background: #0a2e1a; border-color: #22c55e; }
        .callout-danger  { background: #2d0a0a; border-color: #ef4444; }
        .callout strong  { color: #ccd6f6; }

        /* ===================================================================
         * CHECKLIST
         * =================================================================== */
        .checklist {
            list-style: none;
            padding: 0;
        }
        .checklist li {
            padding: 6px 0 6px 28px;
            position: relative;
        }
        .checklist li::before {
            content: '☐';
            position: absolute;
            left: 4px;
            color: #64ffda;
        }

        /* ===================================================================
         * DIRECTORY TREE
         * =================================================================== */
        .tree {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 16px 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            line-height: 1.7;
            overflow-x: auto;
            margin: 14px 0 18px;
            white-space: pre;
        }
        .tree .dir  { color: #569cd6; font-weight: 600; }
        .tree .file { color: #a8b2d1; }
        .tree .desc { color: #6a9955; }

        /* ===================================================================
         * FOOTER
         * =================================================================== */
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #233554;
            font-size: 12px;
            color: #6a7b8d;
            text-align: center;
            line-height: 1.8;
        }

        /* ===================================================================
         * RESPONSIVE
         * =================================================================== */
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
<!-- ======================================================================
     SIDEBAR
     ====================================================================== -->
<nav class="sidebar">
    <div class="sidebar-logo">🏗️ API Generica</div>
    <div class="sidebar-sub">Laravel 13 • PHP 8.5+ • MySQL 8.4</div>

    <div class="sidebar-section">Fundamentos</div>
    <a href="#arquitetura">Camadas &amp; Fluxo</a>
    <a href="#comparativo">Comparativo v1 vs v2</a>
    <a href="#diretorios">Estrutura de Diretórios</a>

    <div class="sidebar-section">Tipos &amp; Precisão Fiscal</div>
    <a href="#valueobjects">Value Objects</a>
    <a href="#casts">Eloquent Casts</a>

    <div class="sidebar-section">Componentes</div>
    <a href="#model">Model</a>
    <a href="#resposta">Padrão de Resposta</a>
    <a href="#repository">Repository (CRUD)</a>
    <a href="#datatables">DataTables (Opt-in)</a>
    <a href="#service">Service</a>
    <a href="#dto">DTO</a>
    <a href="#controller">Controller</a>
    <a href="#excecoes">Exceções</a>

    <div class="sidebar-section">Acesso &amp; Rotas</div>
    <a href="#jwt">Autenticação JWT</a>
    <a href="#rbac">RBAC / Permissões</a>
    <a href="#rotas">Rotas da API</a>

    <div class="sidebar-section">API Docs</div>
    <a href="#swagger">Swagger / OpenAPI</a>

    <div class="sidebar-section">ERP &amp; Fiscal 🆕</div>
    <a href="#cache">Cache Redis</a>
    <a href="#fiscal">Calculadora Fiscal</a>
    <a href="#events">Domain Events</a>
    <a href="#audit">Audit Trail</a>
    <a href="#transactions">Transações</a>

    <div class="sidebar-section">Infraestrutura</div>
    <a href="#ratelimit">Rate Limiting &amp; Filas</a>
    <a href="#comandos">Artisan Make</a>
    <a href="#hierarquia">Hierarquia de Pastas</a>

    <div class="sidebar-section">Padrões</div>
    <a href="#convencoes">Convenções de Código</a>
    <a href="#checklist">Checklist — Novo Domínio</a>
</nav>

<!-- ======================================================================
     MAIN CONTENT
     ====================================================================== -->
<main class="content">



<h1>
    API Generica — Guia do Desenvolvedor
    <small>Arquitetura idealizada por <strong>Rafael Rozgrin</strong> • Laravel 13 • PHP 8.5+ • MySQL 8.4</small>
</h1>

<div class="callout callout-info">
    <strong>📖 Objetivo deste guia:</strong> Documentar a arquitetura, padrões e convenções da API Generica
    para que qualquer desenvolvedor consiga entender, contribuir e criar novos domínios seguindo os padrões do projeto.
</div>

<!-- ==================================================================== -->
<h2 id="arquitetura">1. Arquitetura em Camadas</h2>

<p>Cada requisição percorre as camadas de forma sequencial. Nenhuma camada pode pular outra:</p>

<div class="flow">
    <span class="flow-box">Request HTTP</span>
    <span class="flow-arrow">→</span>
    <span class="flow-box active">Route</span>
    <span class="flow-arrow">→</span>
    <span class="flow-box active">Controller</span>
    <span class="flow-arrow">→</span>
    <span class="flow-box active">Service</span>
    <span class="flow-arrow">→</span>
    <span class="flow-box active">Repository</span>
    <span class="flow-arrow">→</span>
    <span class="flow-box active">Model</span>
    <span class="flow-arrow">→</span>
    <span class="flow-box">MySQL</span>
</div>

<table>
    <thead>
        <tr><th>Camada</th><th>Responsabilidade</th><th>Localização</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Route</strong></td>
            <td>Define endpoints e middlewares. Sem lógica.</td>
            <td><code>routes/api.php</code></td>
        </tr>
        <tr>
            <td><strong>Controller</strong></td>
            <td>Recebe a request, delega para o Service, retorna resposta. Enxuto.</td>
            <td><code>app/Http/Controllers/</code></td>
        </tr>
        <tr>
            <td><strong>Service</strong></td>
            <td>TODA lógica de negócio. Validações, regras, orquestração.</td>
            <td><code>app/Services/</code></td>
        </tr>
        <tr>
            <td><strong>Repository</strong></td>
            <td>Acesso ao banco via Eloquent. Sem lógica de negócio.</td>
            <td><code>app/Repository/</code></td>
        </tr>
        <tr>
            <td><strong>Model</strong></td>
            <td>Representação da tabela. Fillable, casts, relacionamentos.</td>
            <td><code>app/Models/</code></td>
        </tr>
    </tbody>
</table>

<div class="callout callout-danger">
    <strong>⛔ Regra absoluta:</strong> Controllers NÃO podem acessar Repositories diretamente.
    Services NÃO podem usar <code>DB::table()</code> ou queries diretas. Models NÃO podem ter lógica de negócio.
</div>

<!-- ==================================================================== -->
<h2 id="comparativo">2. Comparativo — apidefault vs api-generica</h2>

<p>Entender <em>por que</em> cada padrão existe evita regressões ao código legado. A tabela abaixo documenta as diferenças arquiteturais entre as duas APIs.</p>

<table>
    <thead><tr><th>Aspecto</th><th>apidefault (Laravel 8 — legado)</th><th>api-generica (Laravel 13 — novo)</th></tr></thead>
    <tbody>
        <tr><td>PHP</td><td>7.3 / 8.0</td><td>8.5+ (strict types, readonly, match)</td></tr>
        <tr><td>JWT</td><td><code>tymon/jwt-auth</code> (sem manutenção)</td><td><code>php-open-source-saver/jwt-auth</code> v2</td></tr>
        <tr><td>CORS</td><td><code>fruitcake/laravel-cors</code> (abandonado)</td><td>Nativo em <code>bootstrap/app.php</code></td></tr>
        <tr><td>DTO</td><td>JMS Serializer (incompatível PHP 8.5)</td><td>Readonly classes + RequestBodyConverter</td></tr>
        <tr><td>Valores monetários</td><td><code>float</code> nativo — acumula erros</td><td><code>Money</code> VO com BCMath — 8 decimais exatos</td></tr>
        <tr><td>Arredondamento</td><td>Nenhum padrão definido</td><td>ABNT NBR 5891 via <code>RoundingService</code></td></tr>
        <tr><td>Casts</td><td>Property <code>$casts</code> com <code>'decimal:2'</code></td><td>Método <code>casts(): array</code> + VOs via Cast classes</td></tr>
        <tr><td>DataTables</td><td>Obrigatório em todo Repository</td><td>Opt-in via trait <code>HasDataTables</code></td></tr>
        <tr><td>Cache</td><td>Driver <code>database</code> (lento)</td><td>Redis com tags + TTL por domínio</td></tr>
        <tr><td>RBAC</td><td>Nenhum controle de acesso</td><td>Roles + Permissions + JWT claims + Redis cache</td></tr>
        <tr><td>Audit Trail</td><td>Nenhuma rastreabilidade</td><td>Trait <code>Auditable</code> → <code>webc_auditoria</code></td></tr>
        <tr><td>Transações</td><td><code>DB::transaction()</code> avulso, sem padrão</td><td>Trait <code>WithTransaction</code> em todo Service</td></tr>
        <tr><td>Domain Events</td><td>Acoplamento direto entre domínios</td><td><code>DomainEvent</code> abstrato + EventDispatcher</td></tr>
        <tr><td>Filas</td><td>Driver <code>database</code> (bloqueante)</td><td>Redis com conexões por tipo (fiscal, emails)</td></tr>
        <tr><td>Rate Limiting</td><td>Nenhum</td><td>3 níveis: leitura / escrita / relatórios</td></tr>
        <tr><td>Documentação API</td><td>Nenhuma</td><td>Swagger/OpenAPI + Scalar UI</td></tr>
        <tr><td>Middleware</td><td><code>app/Http/Kernel.php</code></td><td><code>bootstrap/app.php</code> (Laravel 11+)</td></tr>
        <tr><td>Scaffolding</td><td>Comandos make básicos</td><td><code>make:domain</code> com hierarquia + Swagger + Binding</td></tr>
        <tr><td><code>env()</code> em code</td><td>Usado diretamente no código</td><td>Sempre via <code>config()</code> — nunca <code>env()</code> fora de config files</td></tr>
    </tbody>
</table>

<div class="callout callout-info">
    <strong>💡 Regra de ouro:</strong> Ao migrar um domínio do <em>apidefault</em>, nunca copie os padrões antigos.
    Sempre adapte para a nova arquitetura: VOs para monetários, MoneyCast em vez de decimal:2, WithTransaction em vez de DB::transaction() avulso.
</div>

<!-- ==================================================================== -->
<h2 id="diretorios">3. Estrutura de Diretórios</h2>

<div class="tree">
<span class="dir">app/</span>
├── <span class="dir">Casts/</span>                       <span class="desc">← Eloquent Casts para Value Objects 🆕</span>
│   ├── <span class="file">MoneyCast.php</span>            <span class="desc">← DECIMAL(15,8) ↔ Money VO</span>
│   ├── <span class="file">PercentageCast.php</span>       <span class="desc">← decimal ↔ Percentage VO</span>
│   ├── <span class="file">QuantityCast.php</span>         <span class="desc">← decimal ↔ Quantity VO</span>
│   ├── <span class="file">CpfCast.php</span>              <span class="desc">← string ↔ Cpf VO</span>
│   ├── <span class="file">CnpjCast.php</span>             <span class="desc">← string ↔ Cnpj VO</span>
│   └── <span class="file">PeriodCast.php</span>           <span class="desc">← string ↔ Period VO</span>
├── <span class="dir">Console/Commands/</span>           <span class="desc">← Comandos Artisan customizados (make:domain, make:repository, etc.)</span>
│   └── <span class="dir">Concerns/</span>               <span class="desc">← Traits dos comandos (ParsesNameArgument)</span>
├── <span class="dir">DTO/</span>                        <span class="desc">← Data Transfer Objects (readonly classes)</span>
│   └── <span class="dir">{Domínio}/</span>              <span class="desc">← Organizados por domínio (ex: Produto/ProdutoDTO.php)</span>
├── <span class="dir">Events/Domain/</span>              <span class="desc">← Domain Events 🆕</span>
│   ├── <span class="file">DomainEvent.php</span>          <span class="desc">← Classe abstrata base</span>
│   └── <span class="dir">{Domínio}/</span>              <span class="desc">← Events por domínio (ex: Venda/VendaRealizadaEvent.php)</span>
├── <span class="dir">Exceptions/</span>                 <span class="desc">← Exceções customizadas do sistema</span>
│   ├── <span class="file">AlertaException.php</span>     <span class="desc">← Exceção com nível de severidade</span>
│   ├── <span class="file">ApiException.php</span> <span class="desc">← Exceção de regra de negócio</span>
│   └── <span class="file">HandlerExceptionCritical.php</span> <span class="desc">← Trait para exceções críticas (DB down)</span>
├── <span class="dir">Http/</span>
│   ├── <span class="dir">Controllers/</span>            <span class="desc">← Controllers organizados por domínio</span>
│   │   ├── <span class="file">Controller.php</span>      <span class="desc">← Base controller com trait SendResponse</span>
│   │   ├── <span class="file">AuthController.php</span>  <span class="desc">← Autenticação JWT (login, me, refresh)</span>
│   │   └── <span class="dir">{Domínio}/</span>          <span class="desc">← Controllers do domínio</span>
│   └── <span class="dir">Middleware/</span>
│       ├── <span class="file">ApiJwtMiddleware.php</span>          <span class="desc">← Validação de token JWT</span>
│       └── <span class="file">CheckPermissionMiddleware.php</span>  <span class="desc">← Verificação RBAC 🆕</span>
├── <span class="dir">Jobs/</span>                        <span class="desc">← Queue Jobs assíncronos 🆕</span>
│   └── <span class="dir">{Domínio}/</span>              <span class="desc">← Jobs por domínio (ex: Relatorio/GerarRelatorioJob.php)</span>
├── <span class="dir">Models/</span>                     <span class="desc">← Models Eloquent</span>
│   ├── <span class="file">Permission.php</span>           <span class="desc">← RBAC — Permissão 🆕</span>
│   ├── <span class="file">Role.php</span>                 <span class="desc">← RBAC — Papel/Função 🆕</span>
│   ├── <span class="dir">Traits/</span>
│   │   └── <span class="file">Auditable.php</span>        <span class="desc">← Audit Trail automático 🆕</span>
│   └── <span class="dir">{Domínio}/</span>              <span class="desc">← Organizados por domínio</span>
├── <span class="dir">OpenApi/</span>
│   └── <span class="file">OpenApiSpec.php</span>         <span class="desc">← Definição global do Swagger (tags, schemas)</span>
├── <span class="dir">Repository/</span>
│   ├── <span class="dir">Contracts/</span>              <span class="desc">← Interfaces dos repositories</span>
│   │   ├── <span class="file">RepositoryInterface.php</span>  <span class="desc">← Contrato CRUD base</span>
│   │   ├── <span class="file">DataTablesInterface.php</span>  <span class="desc">← Contrato DataTables (opt-in)</span>
│   │   └── <span class="dir">Models/{Domínio}/</span>       <span class="desc">← Interfaces por domínio</span>
│   ├── <span class="dir">Eloquent/</span>               <span class="desc">← Implementações Eloquent</span>
│   │   ├── <span class="file">AbstractEloquentRepository.php</span> <span class="desc">← CRUD genérico</span>
│   │   ├── <span class="file">EloquentRepository.php</span>       <span class="desc">← Configuração de conexão</span>
│   │   └── <span class="dir">Models/{Domínio}/</span>           <span class="desc">← Repositórios por domínio</span>
│   └── <span class="dir">Traits/</span>
│       ├── <span class="file">HasDataTables.php</span>    <span class="desc">← Trait opt-in para DataTables server-side</span>
│       └── <span class="file">CacheableRepository.php</span> <span class="desc">← Cache automático de leitura 🆕</span>
├── <span class="dir">Services/</span>
│   ├── <span class="dir">Auth/</span>
│   │   └── <span class="file">UsuarioLogadoService.php</span> <span class="desc">← Dados do usuário JWT</span>
│   ├── <span class="dir">Cache/</span>
│   │   └── <span class="file">CacheService.php</span>         <span class="desc">← Cache Redis com tags por domínio 🆕</span>
│   ├── <span class="dir">Fiscal/</span>
│   │   ├── <span class="file">RoundingService.php</span>      <span class="desc">← Arredondamento ABNT NBR 5891 🆕</span>
│   │   └── <span class="file">FiscalCalculatorService.php</span> <span class="desc">← Cálculos fiscais BCMath 🆕</span>
│   ├── <span class="dir">Extensions/</span>
│   │   ├── <span class="file">BindsRepositorios.php</span>      <span class="desc">← Registro de bindings IoC</span>
│   │   ├── <span class="file">RequestBodyConverter.php</span>    <span class="desc">← Conversor JSON→DTO</span>
│   │   └── <span class="file">RequestBodyConverterInterface.php</span> <span class="desc">← Interface marcadora</span>
│   ├── <span class="dir">ResponseApi/</span>
│   │   ├── <span class="file">ResponseApi.php</span>     <span class="desc">← Resposta JSON para produção</span>
│   │   └── <span class="file">ResponseApiDev.php</span>  <span class="desc">← Resposta JSON com debug (dev)</span>
│   ├── <span class="dir">Traits/</span>
│   │   ├── <span class="file">SendResponse.php</span>    <span class="desc">← Trait que monta a resposta adequada ao ambiente</span>
│   │   └── <span class="file">WithTransaction.php</span> <span class="desc">← Gerenciamento de transações DB 🆕</span>
│   └── <span class="dir">{Domínio}/</span>              <span class="desc">← Services com lógica de negócio por domínio</span>
└── <span class="dir">ValueObjects/</span>               <span class="desc">← Value Objects fiscais 🆕</span>
    ├── <span class="file">Money.php</span>                <span class="desc">← Aritmética monetária BCMath (8 decimais)</span>
    ├── <span class="file">Percentage.php</span>           <span class="desc">← Taxas fiscais (ICMS, ISS, PIS, COFINS)</span>
    ├── <span class="file">Quantity.php</span>             <span class="desc">← Quantidades com casas decimais</span>
    ├── <span class="file">Cpf.php</span>                  <span class="desc">← CPF com validação de dígitos verificadores</span>
    ├── <span class="file">Cnpj.php</span>                 <span class="desc">← CNPJ com validação de dígitos verificadores</span>
    ├── <span class="file">Period.php</span>               <span class="desc">← Período fiscal (data início / data fim)</span>
    └── <span class="file">RoundingMode.php</span>         <span class="desc">← Enum: HALF_UP, HALF_DOWN, HALF_EVEN, TRUNCATE</span>
</div>

<!-- ==================================================================== -->
<h2 id="valueobjects">4. Value Objects — Precisão Fiscal</h2>

<div class="callout callout-danger">
    <strong>⛔ Regra crítica:</strong> NUNCA use <code>float</code> para valores monetários. Sempre use <code>Money</code> VO com BCMath. PHP <code>float</code> acumula erros de ponto flutuante que causam diferenças de centavos em NF-e.
</div>

<h3>Por que BCMath?</h3>
<pre><code><span class="cm">// ❌ ERRADO — float acumula erros</span>
<span class="var">$total</span> = <span class="num">0.1</span> + <span class="num">0.2</span>; <span class="cm">// = 0.30000000000000004 em PHP!</span>

<span class="cm">// ✅ CORRETO — BCMath é exato</span>
<span class="var">$total</span> = <span class="fn">bcadd</span>(<span class="str">'0.1'</span>, <span class="str">'0.2'</span>, <span class="num">8</span>); <span class="cm">// = '0.20000000'</span></code></pre>

<h3>Money — Aritmética Monetária</h3>
<pre><code><span class="kw">use</span> <span class="tp">App\ValueObjects\Money</span>;

<span class="cm">// Criação</span>
<span class="var">$preco</span>   = Money::<span class="fn">of</span>(<span class="str">'29.90'</span>);    <span class="cm">// sempre de string</span>
<span class="var">$desconto</span> = Money::<span class="fn">of</span>(<span class="str">'5.00'</span>);

<span class="cm">// Aritmética — todos os métodos retornam nova instância (imutável)</span>
<span class="var">$subtotal</span>  = <span class="var">$preco</span>-><span class="fn">sub</span>(<span class="var">$desconto</span>);      <span class="cm">// R$ 24.90</span>
<span class="var">$comJuros</span>  = <span class="var">$preco</span>-><span class="fn">mul</span>(<span class="str">'1.025'</span>);       <span class="cm">// +2,5% de juros</span>
<span class="var">$dividido</span>  = <span class="var">$preco</span>-><span class="fn">div</span>(<span class="str">'3'</span>);           <span class="cm">// divisão exata</span>

<span class="cm">// Arredondamento — SEMPRE ao final, nunca no meio do cálculo</span>
<span class="var">$final</span>     = <span class="var">$subtotal</span>-><span class="fn">round</span>(<span class="num">2</span>);         <span class="cm">// HALF_UP (ABNT NBR 5891)</span>

<span class="cm">// Comparação</span>
<span class="var">$preco</span>-><span class="fn">equals</span>(<span class="var">$desconto</span>);      <span class="cm">// false</span>
<span class="var">$preco</span>-><span class="fn">greaterThan</span>(<span class="var">$desconto</span>); <span class="cm">// true</span>
<span class="var">$preco</span>-><span class="fn">isZero</span>();               <span class="cm">// false</span>

<span class="cm">// Saída</span>
<span class="var">$preco</span>-><span class="fn">toString</span>();  <span class="cm">// '29.90000000' — use para o banco</span>
<span class="var">$preco</span>-><span class="fn">toFloat</span>();   <span class="cm">// 29.9 — APENAS para display</span></code></pre>

<h3>Percentage — Taxas Fiscais</h3>
<pre><code><span class="kw">use</span> <span class="tp">App\ValueObjects\Percentage</span>;
<span class="kw">use</span> <span class="tp">App\ValueObjects\Money</span>;

<span class="var">$aliquotaIcms</span> = Percentage::<span class="fn">of</span>(<span class="str">'12.00'</span>);    <span class="cm">// 12%</span>
<span class="var">$aliquotaPis</span>  = Percentage::<span class="fn">of</span>(<span class="str">'0.65'</span>);     <span class="cm">// 0.65%</span>

<span class="var">$base</span>      = Money::<span class="fn">of</span>(<span class="str">'1000.00'</span>);
<span class="var">$valorIcms</span> = <span class="var">$aliquotaIcms</span>-><span class="fn">applyTo</span>(<span class="var">$base</span>); <span class="cm">// Money de R$ 120.00</span>

<span class="var">$aliquotaIcms</span>-><span class="fn">toDecimal</span>();  <span class="cm">// '0.12000000'</span>
<span class="var">$aliquotaIcms</span>-><span class="fn">toString</span>();   <span class="cm">// '12.00000000'</span></code></pre>

<h3>Quantity — Quantidades de Produto</h3>
<pre><code><span class="kw">use</span> <span class="tp">App\ValueObjects\Quantity</span>;

<span class="var">$quantidade</span>   = Quantity::<span class="fn">of</span>(<span class="str">'2.500'</span>);         <span class="cm">// 2,500 kg</span>
<span class="var">$precoUnit</span>    = Money::<span class="fn">of</span>(<span class="str">'15.80'</span>);
<span class="var">$valorTotal</span>   = <span class="var">$quantidade</span>-><span class="fn">times</span>(<span class="var">$precoUnit</span>); <span class="cm">// Money de R$ 39.50</span></code></pre>

<h3>Cpf / Cnpj — Documentos Fiscais</h3>
<pre><code><span class="kw">use</span> <span class="tp">App\ValueObjects\Cpf</span>;
<span class="kw">use</span> <span class="tp">App\ValueObjects\Cnpj</span>;

<span class="cm">// Aceita com ou sem formatação — valida dígitos verificadores</span>
<span class="var">$cpf</span>  = Cpf::<span class="fn">of</span>(<span class="str">'123.456.789-09'</span>);
<span class="var">$cnpj</span> = Cnpj::<span class="fn">of</span>(<span class="str">'11.222.333/0001-81'</span>);

<span class="var">$cpf</span>-><span class="fn">digits</span>();     <span class="cm">// '12345678909' — apenas dígitos (para o banco)</span>
<span class="var">$cpf</span>-><span class="fn">formatted</span>(); <span class="cm">// '123.456.789-09'</span>

<span class="cm">// CPF inválido → ApiException 422</span>
Cpf::<span class="fn">of</span>(<span class="str">'111.111.111-11'</span>); <span class="cm">// throw</span></code></pre>

<h3>Period — Períodos Fiscais</h3>
<pre><code><span class="kw">use</span> <span class="tp">App\ValueObjects\Period</span>;

<span class="var">$competencia</span> = Period::<span class="fn">of</span>(<span class="str">'2025-01-01'</span>, <span class="str">'2025-01-31'</span>);

<span class="var">$competencia</span>-><span class="fn">contains</span>(<span class="kw">new</span> DateTime(<span class="str">'2025-01-15'</span>));  <span class="cm">// true</span>
<span class="var">$competencia</span>-><span class="fn">contains</span>(<span class="kw">new</span> DateTime(<span class="str">'2025-02-01'</span>));  <span class="cm">// false</span>
<span class="var">$competencia</span>-><span class="fn">days</span>();     <span class="cm">// 31</span>
<span class="var">$competencia</span>-><span class="fn">toString</span>(); <span class="cm">// '2025-01-01/2025-01-31' (para banco)</span></code></pre>

<!-- ==================================================================== -->
<h2 id="casts">5. Eloquent Casts para Value Objects</h2>

<p>Casts fazem a ponte automática entre o banco (strings/decimais) e os Value Objects. Ficam em <code>app/Casts/</code>.</p>

<div class="callout callout-warning">
    <strong>🏦 Regra de ouro:</strong> Colunas monetárias devem ser <code>DECIMAL(15,8)</code> no banco. Isso suporta até R$&nbsp;9.999.999,99999999 com 8 casas de precisão.
</div>

<pre><code><span class="kw">use</span> <span class="tp">App\Casts\MoneyCast</span>;
<span class="kw">use</span> <span class="tp">App\Casts\PercentageCast</span>;
<span class="kw">use</span> <span class="tp">App\Casts\QuantityCast</span>;
<span class="kw">use</span> <span class="tp">App\Casts\CpfCast</span>;
<span class="kw">use</span> <span class="tp">App\Casts\CnpjCast</span>;
<span class="kw">use</span> <span class="tp">App\Casts\PeriodCast</span>;

<span class="kw">class</span> <span class="tp">Produto</span> <span class="kw">extends</span> <span class="tp">Model</span>
{
    <span class="kw">protected function</span> <span class="fn">casts</span>(): <span class="tp">array</span>
    {
        <span class="kw">return</span> [
            <span class="cm">// Valores monetários (DECIMAL 15,8 no banco)</span>
            <span class="str">'preco'</span>             => MoneyCast::<span class="kw">class</span>,
            <span class="str">'custo'</span>             => MoneyCast::<span class="kw">class</span>,
            <span class="str">'preco_promocional'</span> => MoneyCast::<span class="kw">class</span>,

            <span class="cm">// Percentuais fiscais</span>
            <span class="str">'aliquota_icms'</span>    => PercentageCast::<span class="kw">class</span>,
            <span class="str">'desconto_max'</span>     => PercentageCast::<span class="kw">class</span>,
            <span class="str">'comissao'</span>         => PercentageCast::<span class="kw">class</span>,

            <span class="cm">// Quantidades — precisão padrão 8, ou customizada ':3' para kg</span>
            <span class="str">'estoque_minimo'</span>   => QuantityCast::<span class="kw">class</span>,
            <span class="str">'peso_kg'</span>          => QuantityCast::<span class="kw">class</span> . <span class="str">':3'</span>,

            <span class="cm">// Documentos — armazenados só com dígitos no banco</span>
            <span class="str">'cpf_responsavel'</span>  => CpfCast::<span class="kw">class</span>,
            <span class="str">'cnpj_fabricante'</span>  => CnpjCast::<span class="kw">class</span>,
        ];
    }
}

<span class="cm">// Ao LER do banco — cast automático para VO</span>
<span class="var">$produto</span>-><span class="var">preco</span>;           <span class="cm">// instância de Money</span>
<span class="var">$produto</span>-><span class="var">aliquota_icms</span>;   <span class="cm">// instância de Percentage</span>

<span class="cm">// Operações direto no VO</span>
<span class="var">$total</span> = <span class="var">$produto</span>-><span class="var">preco</span>-><span class="fn">mul</span>(<span class="str">'3'</span>); <span class="cm">// Money: 3 unidades</span>

<span class="cm">// Ao SALVAR — cast automático de volta para string</span>
<span class="var">$produto</span>-><span class="var">preco</span> = Money::<span class="fn">of</span>(<span class="str">'59.90'</span>);
<span class="var">$produto</span>-><span class="fn">save</span>(); <span class="cm">// grava '59.90000000' no banco</span></code></pre>

<!-- ==================================================================== -->
<h2 id="model">6. Model</h2>

<p>Models representam tabelas do banco. Tabelas legadas (prefixo <code>webc_</code>) usam timestamps em português.</p>

<pre><code><span class="at">&lt;?php</span>

<span class="kw">namespace</span> <span class="tp">App\Models</span>;

<span class="kw">use</span> <span class="tp">Illuminate\Database\Eloquent\Model</span>;
<span class="kw">use</span> <span class="tp">App\Casts\MoneyCast</span>;         <span class="cm">// ← DECIMAL(15,8) ↔ Money VO</span>
<span class="kw">use</span> <span class="tp">App\Casts\PercentageCast</span>;    <span class="cm">// ← decimal ↔ Percentage VO</span>
<span class="kw">use</span> <span class="tp">App\Casts\QuantityCast</span>;      <span class="cm">// ← decimal ↔ Quantity VO</span>
<span class="cm">// use App\Models\Traits\Auditable; // ← descomente para audit trail</span>

<span class="cm">/**
 * Model Eloquent para Produto.
 *
 * Colunas monetárias: DECIMAL(15,8) no banco + MoneyCast no PHP.
 * NUNCA use 'decimal:2' para valores fiscais.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */</span>
<span class="kw">class</span> <span class="tp">Produto</span> <span class="kw">extends</span> <span class="tp">Model</span>
{
    <span class="cm">// use Auditable;</span>

    <span class="kw">protected</span> <span class="var">$table</span> = <span class="str">'webc_produto'</span>;

    <span class="cm">// Timestamps legados em português</span>
    <span class="kw">const</span> <span class="var">CREATED_AT</span> = <span class="str">'data_criacao'</span>;
    <span class="kw">const</span> <span class="var">UPDATED_AT</span> = <span class="str">'data_alteracao'</span>;

    <span class="kw">protected</span> <span class="var">$fillable</span> = [
        <span class="str">'nome'</span>, <span class="str">'descricao'</span>, <span class="str">'preco'</span>, <span class="str">'ativo'</span>,
        <span class="str">'aliquota_icms'</span>, <span class="str">'estoque'</span>, <span class="str">'categoria_id'</span>,
    ];

    <span class="kw">protected function</span> <span class="fn">casts</span>(): <span class="tp">array</span>
    {
        <span class="kw">return</span> [
            <span class="cm">// ✅ Monetário — DECIMAL(15,8) + MoneyCast (nunca 'decimal:2')</span>
            <span class="str">'preco'</span>         => MoneyCast::<span class="kw">class</span>,
            <span class="str">'custo'</span>         => MoneyCast::<span class="kw">class</span>,
            <span class="cm">// ✅ Fiscal</span>
            <span class="str">'aliquota_icms'</span> => PercentageCast::<span class="kw">class</span>,
            <span class="cm">// ✅ Quantidade</span>
            <span class="str">'estoque'</span>       => QuantityCast::<span class="kw">class</span>,
            <span class="str">'data_criacao'</span>  => <span class="str">'datetime'</span>,
        ];
    }
}</code></pre>

<!-- ==================================================================== -->
<h2 id="resposta">7. Padrão de Resposta da API</h2>

<p>Todas as respostas passam pela trait <code>SendResponse</code>, que retorna <code>ResponseApi</code> em produção e <code>ResponseApiDev</code> em desenvolvimento.</p>

<h4>✅ Sucesso (200)</h4>
<pre><code>{
    <span class="str">"conteudo"</span>: { <span class="str">"id"</span>: <span class="num">1</span>, <span class="str">"nome"</span>: <span class="str">"Produto A"</span> },
    <span class="str">"msg"</span>: <span class="str">""</span>,
    <span class="str">"code"</span>: <span class="num">200</span>
}</code></pre>

<h4>❌ Erro — Produção (400)</h4>
<pre><code>{
    <span class="str">"conteudo"</span>: <span class="kw">null</span>,
    <span class="str">"msg"</span>: <span class="str">"Produto não encontrado."</span>,
    <span class="str">"code"</span>: <span class="num">404</span>
}</code></pre>

<h4>🐛 Erro — Desenvolvimento (com debug)</h4>
<pre><code>{
    <span class="str">"conteudo"</span>: <span class="kw">null</span>,
    <span class="str">"msg"</span>: <span class="str">"Produto não encontrado."</span>,
    <span class="str">"code"</span>: <span class="num">404</span>,
    <span class="str">"file"</span>: <span class="str">"/app/Services/Produto/ProdutoService.php"</span>,
    <span class="str">"line"</span>: <span class="num">42</span>,
    <span class="str">"exception"</span>: <span class="str">"App\\Exceptions\\ApiException"</span>,
    <span class="str">"trace"</span>: <span class="str">"#0 ..."</span>
}</code></pre>

<h4>Uso no Controller</h4>
<pre><code><span class="cm">// A trait SendResponse já está no Controller base</span>
<span class="kw">return</span> response()-><span class="fn">json</span>(
    data: <span class="var">$this</span>-><span class="fn">send</span>(conteudo: <span class="var">$dados</span>),
);

<span class="cm">// Com mensagem e código customizado</span>
<span class="kw">return</span> response()-><span class="fn">json</span>(
    data: <span class="var">$this</span>-><span class="fn">send</span>(
        conteudo: <span class="var">$criado</span>,
        code: Response::<span class="var">HTTP_CREATED</span>,
        msg: <span class="str">'Produto criado com sucesso.'</span>,
    ),
    status: Response::<span class="var">HTTP_CREATED</span>,
);</code></pre>

<!-- ==================================================================== -->
<h2 id="repository">8. Repository — CRUD Base</h2>

<p>O padrão Repository separa o acesso ao banco da lógica de negócio. Todos os repositories implementam <code>RepositoryInterface</code> que define operações CRUD genéricas:</p>

<table>
    <thead><tr><th>Método</th><th>Retorno</th><th>Descrição</th></tr></thead>
    <tbody>
        <tr><td><code>find($id)</code></td><td><code>?object</code></td><td>Busca por ID</td></tr>
        <tr><td><code>findAll()</code></td><td><code>?object</code></td><td>Retorna todos</td></tr>
        <tr><td><code>findBy($criteria, ...)</code></td><td><code>?object</code></td><td>Busca por critérios</td></tr>
        <tr><td><code>findOneBy($criteria)</code></td><td><code>?object</code></td><td>Busca um único registro</td></tr>
        <tr><td><code>paginate($perPage)</code></td><td><code>mixed</code></td><td>Paginação</td></tr>
        <tr><td><code>create($data)</code></td><td><code>object</code></td><td>Cria novo registro</td></tr>
        <tr><td><code>update($data, $id)</code></td><td><code>bool</code></td><td>Atualiza por ID</td></tr>
        <tr><td><code>delete($id)</code></td><td><code>bool</code></td><td>Remove por ID</td></tr>
    </tbody>
</table>

<h3>Exemplo Completo — Repository Simples (sem DataTables)</h3>

<h4>1. Interface</h4>
<pre><code><span class="at">&lt;?php</span>

<span class="kw">namespace</span> <span class="tp">App\Repository\Contracts\Models\Produto</span>;

<span class="kw">use</span> <span class="tp">App\Repository\Contracts\RepositoryInterface</span>;

<span class="cm">/**
 * Contrato para o repositório de Produto.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */</span>
<span class="kw">interface</span> <span class="tp">ProdutoRepositoryInterface</span> <span class="kw">extends</span> <span class="tp">RepositoryInterface</span>
{
    <span class="cm">// Métodos específicos do domínio Produto</span>
    <span class="kw">public function</span> <span class="fn">findByCategoria</span>(<span class="tp">int</span> <span class="var">$categoriaId</span>): ?<span class="tp">object</span>;
}</code></pre>

<h4>2. Implementação Eloquent</h4>
<pre><code><span class="at">&lt;?php</span>

<span class="kw">namespace</span> <span class="tp">App\Repository\Eloquent\Models\Produto</span>;

<span class="kw">use</span> <span class="tp">App\Models\Produto</span>;
<span class="kw">use</span> <span class="tp">App\Repository\Eloquent\EloquentRepository</span>;
<span class="kw">use</span> <span class="tp">App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface</span>;

<span class="cm">/**
 * Implementação Eloquent do repositório de Produto.
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */</span>
<span class="kw">class</span> <span class="tp">ProdutoEloquentRepository</span> <span class="kw">extends</span> <span class="tp">EloquentRepository</span>
    <span class="kw">implements</span> <span class="tp">ProdutoRepositoryInterface</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(<span class="tp">Produto</span> <span class="var">$model</span>)
    {
        <span class="kw">parent</span>::<span class="fn">__construct</span>(<span class="var">$model</span>);
    }

    <span class="kw">public function</span> <span class="fn">findByCategoria</span>(<span class="tp">int</span> <span class="var">$categoriaId</span>): ?<span class="tp">object</span>
    {
        <span class="kw">return</span> <span class="var">$this</span>-><span class="var">model</span>
            -><span class="fn">where</span>(<span class="str">'categoria_id'</span>, <span class="var">$categoriaId</span>)
            -><span class="fn">get</span>();
    }
}</code></pre>

<h4>3. Registro no BindsRepositorios</h4>
<pre><code><span class="cm">// app/Services/Extensions/BindsRepositorios.php</span>
<span class="var">$app</span>-><span class="fn">bind</span>(
    \<span class="tp">App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface</span>::<span class="kw">class</span>,
    \<span class="tp">App\Repository\Eloquent\Models\Produto\ProdutoEloquentRepository</span>::<span class="kw">class</span>,
);</code></pre>

<!-- ==================================================================== -->
<h2 id="datatables">9. DataTables — Suporte Opt-in</h2>

<p>DataTables server-side é <strong>opcional</strong>. Apenas repositories que precisam fornecer dados para jQuery DataTables devem implementar <code>DataTablesInterface</code> + usar a trait <code>HasDataTables</code>.</p>

<div class="callout callout-info">
    <strong>📌 Regra:</strong> Nem todo repository precisa de DataTables. O padrão é NÃO ter. Adicione apenas quando necessário via <code>--datatables</code>.
</div>

<h3>Exemplo — Repository COM DataTables</h3>

<pre><code><span class="at">&lt;?php</span>

<span class="kw">namespace</span> <span class="tp">App\Repository\Eloquent\Models\Produto</span>;

<span class="kw">use</span> <span class="tp">App\Models\Produto</span>;
<span class="kw">use</span> <span class="tp">App\Repository\Eloquent\EloquentRepository</span>;
<span class="kw">use</span> <span class="tp">App\Repository\Traits\HasDataTables</span>;
<span class="kw">use</span> <span class="tp">App\Repository\Contracts\DataTablesInterface</span>;
<span class="kw">use</span> <span class="tp">App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface</span>;

<span class="cm">/**
 * Repositório de Produto com suporte a DataTables server-side.
 */</span>
<span class="kw">class</span> <span class="tp">ProdutoEloquentRepository</span> <span class="kw">extends</span> <span class="tp">EloquentRepository</span>
    <span class="kw">implements</span> <span class="tp">ProdutoRepositoryInterface</span>, <span class="tp">DataTablesInterface</span>
{
    <span class="kw">use</span> <span class="tp">HasDataTables</span>;

    <span class="cm">// Colunas onde a busca global pesquisa (OR entre elas)</span>
    <span class="kw">protected array</span> <span class="var">$searchableColumns</span> = [<span class="str">'nome'</span>, <span class="str">'sku'</span>, <span class="str">'descricao'</span>];

    <span class="cm">// Colunas ordenáveis — índice = posição da coluna no frontend</span>
    <span class="kw">protected array</span> <span class="var">$orderableColumns</span> = [<span class="str">'id'</span>, <span class="str">'nome'</span>, <span class="str">'preco'</span>, <span class="str">'data_criacao'</span>];

    <span class="kw">protected string</span> <span class="var">$defaultOrderColumn</span> = <span class="str">'data_criacao'</span>;
    <span class="kw">protected string</span> <span class="var">$defaultOrderDirection</span> = <span class="str">'desc'</span>;

    <span class="kw">public function</span> <span class="fn">__construct</span>(<span class="tp">Produto</span> <span class="var">$model</span>)
    {
        <span class="kw">parent</span>::<span class="fn">__construct</span>(<span class="var">$model</span>);
    }

    <span class="cm">// Personaliza a saída para o DataTables</span>
    <span class="kw">protected function</span> <span class="fn">transformForDataTables</span>(<span class="tp">mixed</span> <span class="var">$item</span>): <span class="tp">array</span>
    {
        <span class="kw">return</span> [
            <span class="str">'id'</span>    => <span class="var">$item</span>-><span class="var">id</span>,
            <span class="str">'nome'</span>  => <span class="var">$item</span>-><span class="var">nome</span>,
            <span class="cm">// $item->preco é Money VO — use round(2)->toFloat() para display</span>
            <span class="str">'preco'</span> => <span class="str">'R$ '</span> . <span class="fn">number_format</span>(<span class="var">$item</span>-><span class="var">preco</span>-><span class="fn">round</span>(<span class="num">2</span>)-><span class="fn">toFloat</span>(), <span class="num">2</span>, <span class="str">','</span>, <span class="str">'.'</span>),
            <span class="str">'ativo'</span> => <span class="var">$item</span>-><span class="var">ativo</span> === <span class="str">'S'</span> ? <span class="str">'Ativo'</span> : <span class="str">'Inativo'</span>,
        ];
    }
}</code></pre>

<h4>Resposta do DataTables</h4>
<pre><code>{
    <span class="str">"draw"</span>: <span class="num">1</span>,
    <span class="str">"recordsTotal"</span>: <span class="num">523</span>,
    <span class="str">"recordsFiltered"</span>: <span class="num">42</span>,
    <span class="str">"data"</span>: [
        { <span class="str">"id"</span>: <span class="num">1</span>, <span class="str">"nome"</span>: <span class="str">"Produto A"</span>, <span class="str">"preco"</span>: <span class="str">"R$ 29,90"</span>, <span class="str">"ativo"</span>: <span class="str">"Ativo"</span> },
        { <span class="str">"id"</span>: <span class="num">2</span>, <span class="str">"nome"</span>: <span class="str">"Produto B"</span>, <span class="str">"preco"</span>: <span class="str">"R$ 149,00"</span>, <span class="str">"ativo"</span>: <span class="str">"Inativo"</span> }
    ]
}</code></pre>

<!-- ==================================================================== -->
<h2 id="service">10. Service — Lógica de Negócio</h2>

<p>Services são o <strong>coração da aplicação</strong>. TODA regra de negócio fica aqui. Sempre usam a trait <code>WithTransaction</code> para operações de escrita — rollback automático em caso de falha.</p>

<pre><code><span class="at">&lt;?php</span>

<span class="kw">declare</span>(strict_types=<span class="num">1</span>);

<span class="kw">namespace</span> <span class="tp">App\Services\Produto</span>;

<span class="kw">use</span> <span class="tp">App\DTO\Produto\ProdutoDTO</span>;
<span class="kw">use</span> <span class="tp">App\Exceptions\ApiException</span>;
<span class="kw">use</span> <span class="tp">App\Services\Auth\UsuarioLogadoService</span>;
<span class="kw">use</span> <span class="tp">App\Services\Extensions\RequestBodyConverter</span>;
<span class="kw">use</span> <span class="tp">App\Services\Traits\WithTransaction</span>;
<span class="kw">use</span> <span class="tp">App\Repository\Contracts\Models\Produto\ProdutoRepositoryInterface</span>;
<span class="kw">use</span> <span class="tp">Symfony\Component\HttpFoundation\Response</span>;

<span class="cm">/**
 * Serviço de lógica de negócio para Produto.
 *
 * A trait WithTransaction fornece:
 *   - $this->transaction(fn() => ...) — bloco atômico com rollback automático
 *   - $this->transactionWithRetry(fn() => ..., attempts: 3) — retry em deadlocks
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */</span>
<span class="kw">class</span> <span class="tp">ProdutoService</span>
{
    <span class="kw">use</span> <span class="tp">WithTransaction</span>;

    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private readonly</span> <span class="tp">ProdutoRepositoryInterface</span> <span class="var">$repository</span>,
        <span class="kw">private readonly</span> <span class="tp">UsuarioLogadoService</span> <span class="var">$usuarioLogado</span>,
        <span class="kw">private readonly</span> <span class="tp">RequestBodyConverter</span> <span class="var">$converter</span>,
    ) {}

    <span class="cm">/**
     * Lista produtos com paginação.
     */</span>
    <span class="kw">public function</span> <span class="fn">listar</span>(<span class="tp">int</span> <span class="var">$perPage</span> = <span class="num">15</span>): <span class="tp">mixed</span>
    {
        <span class="kw">return</span> <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">paginate</span>(perPage: <span class="var">$perPage</span>);
    }

    <span class="cm">/**
     * Busca produto pelo ID ou lança 404.
     */</span>
    <span class="kw">public function</span> <span class="fn">buscarPorId</span>(<span class="tp">int</span> <span class="var">$id</span>): <span class="tp">object</span>
    {
        <span class="var">$produto</span> = <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">find</span>(id: <span class="var">$id</span>);

        <span class="kw">if</span> (<span class="var">$produto</span> === <span class="kw">null</span>) {
            <span class="kw">throw new</span> <span class="tp">ApiException</span>(
                msg: <span class="str">'Produto não encontrado.'</span>,
                code: Response::<span class="var">HTTP_NOT_FOUND</span>,
            );
        }

        <span class="kw">return</span> <span class="var">$produto</span>;
    }

    <span class="cm">/**
     * Cria produto — envolvido em transaction para rollback automático.
     */</span>
    <span class="kw">public function</span> <span class="fn">criar</span>(): <span class="tp">object</span>
    {
        <span class="var">$dto</span> = <span class="var">$this</span>-><span class="var">converter</span>-><span class="fn">deserialize</span>(<span class="kw">new</span> <span class="tp">ProdutoDTO</span>());

        <span class="kw">return</span> <span class="var">$this</span>-><span class="fn">transaction</span>(<span class="kw">function</span> () <span class="kw">use</span> (<span class="var">$dto</span>) {
            <span class="kw">return</span> <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">create</span>([
                <span class="str">'nome'</span>       => <span class="var">$dto</span>-><span class="var">nome</span>,
                <span class="str">'preco'</span>      => <span class="var">$dto</span>-><span class="var">preco</span>,
                <span class="str">'ativo'</span>      => <span class="var">$dto</span>-><span class="var">ativo</span>,
                <span class="str">'criado_por'</span> => <span class="var">$this</span>-><span class="var">usuarioLogado</span>-><span class="fn">getId</span>(),
            ]);
            <span class="cm">// event(new ProdutoCriadoEvent($produto->id)); // ← descomente para disparar evento</span>
        });
    }

    <span class="cm">/**
     * Atualiza produto — transaction garante consistência.
     */</span>
    <span class="kw">public function</span> <span class="fn">atualizar</span>(<span class="tp">int</span> <span class="var">$id</span>): <span class="tp">bool</span>
    {
        <span class="var">$this</span>-><span class="fn">buscarPorId</span>(id: <span class="var">$id</span>);
        <span class="var">$dto</span> = <span class="var">$this</span>-><span class="var">converter</span>-><span class="fn">deserialize</span>(<span class="kw">new</span> <span class="tp">ProdutoDTO</span>());

        <span class="kw">return</span> <span class="var">$this</span>-><span class="fn">transaction</span>(<span class="kw">fn</span>() => <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">update</span>(
            data: [<span class="str">'nome'</span> => <span class="var">$dto</span>-><span class="var">nome</span>, <span class="str">'preco'</span> => <span class="var">$dto</span>-><span class="var">preco</span>],
            id: <span class="var">$id</span>,
        ));
    }
}</code></pre>

<!-- ==================================================================== -->
<h2 id="dto">11. DTO — Data Transfer Object</h2>

<p>DTOs são <code>readonly class</code> que representam o corpo da request. Usam <code>RequestBodyConverterInterface</code> como marcador e incluem anotações Swagger.</p>

<pre><code><span class="at">&lt;?php</span>

<span class="kw">namespace</span> <span class="tp">App\DTO\Produto</span>;

<span class="kw">use</span> <span class="tp">App\Services\Extensions\RequestBodyConverterInterface</span>;
<span class="kw">use</span> <span class="tp">OpenApi\Attributes</span> <span class="kw">as</span> <span class="tp">OA</span>;

<span class="at">#[OA\Schema(</span>
    <span class="var">schema</span>: <span class="str">'ProdutoDTO'</span>,
    <span class="var">title</span>: <span class="str">'Produto'</span>,
    <span class="var">required</span>: [<span class="str">'nome'</span>, <span class="str">'preco'</span>],
<span class="at">)]</span>
<span class="kw">readonly class</span> <span class="tp">ProdutoDTO</span> <span class="kw">implements</span> <span class="tp">RequestBodyConverterInterface</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="at">#[OA\Property(</span><span class="var">description</span>: <span class="str">'ID'</span>, <span class="var">example</span>: <span class="num">1</span><span class="at">)]</span>
        <span class="kw">public</span> ?<span class="tp">int</span> <span class="var">$id</span> = <span class="kw">null</span>,

        <span class="at">#[OA\Property(</span><span class="var">description</span>: <span class="str">'Nome do produto'</span>, <span class="var">example</span>: <span class="str">'Produto A'</span><span class="at">)]</span>
        <span class="kw">public</span> <span class="tp">string</span> <span class="var">$nome</span> = <span class="str">''</span>,

        <span class="at">#[OA\Property(</span><span class="var">description</span>: <span class="str">'Preço unitário'</span>, <span class="var">example</span>: <span class="num">29.90</span><span class="at">)]</span>
        <span class="kw">public</span> <span class="tp">float</span> <span class="var">$preco</span> = <span class="num">0.0</span>,

        <span class="at">#[OA\Property(</span><span class="var">description</span>: <span class="str">'Status (S/N)'</span>, <span class="var">example</span>: <span class="str">'S'</span><span class="at">)]</span>
        <span class="kw">public</span> <span class="tp">string</span> <span class="var">$ativo</span> = <span class="str">'S'</span>,
    ) {}
}</code></pre>

<!-- ==================================================================== -->
<h2 id="controller">12. Controller</h2>

<p>Controllers são <strong>enxutos</strong>. Recebem a request, delegam ao Service e retornam usando <code>$this->send()</code>. Incluem anotações Swagger com PHP Attributes.</p>

<pre><code><span class="at">&lt;?php</span>

<span class="kw">namespace</span> <span class="tp">App\Http\Controllers\Produto</span>;

<span class="kw">use</span> <span class="tp">App\Http\Controllers\Controller</span>;
<span class="kw">use</span> <span class="tp">App\Services\Produto\ProdutoService</span>;
<span class="kw">use</span> <span class="tp">Illuminate\Http\JsonResponse</span>;
<span class="kw">use</span> <span class="tp">OpenApi\Attributes</span> <span class="kw">as</span> <span class="tp">OA</span>;

<span class="kw">class</span> <span class="tp">ProdutoController</span> <span class="kw">extends</span> <span class="tp">Controller</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private readonly</span> <span class="tp">ProdutoService</span> <span class="var">$service</span>,
    ) {}

    <span class="at">#[OA\Get(</span>
        <span class="var">path</span>: <span class="str">'/produtos'</span>,
        <span class="var">summary</span>: <span class="str">'Listar Produtos'</span>,
        <span class="var">tags</span>: [<span class="str">'Produto'</span>],
        <span class="var">security</span>: [[<span class="str">'bearerAuth'</span> => []]],
        <span class="var">responses</span>: [
            <span class="kw">new</span> <span class="tp">OA\Response</span>(<span class="var">response</span>: <span class="num">200</span>, <span class="var">description</span>: <span class="str">'Sucesso'</span>,
                <span class="var">content</span>: <span class="kw">new</span> <span class="tp">OA\JsonContent</span>(<span class="var">ref</span>: <span class="str">'#/components/schemas/RespostaPadrao'</span>)),
        ],
    <span class="at">)]</span>
    <span class="kw">public function</span> <span class="fn">index</span>(): <span class="tp">JsonResponse</span>
    {
        <span class="kw">return</span> response()-><span class="fn">json</span>(
            data: <span class="var">$this</span>-><span class="fn">send</span>(conteudo: <span class="var">$this</span>-><span class="var">service</span>-><span class="fn">listar</span>()),
        );
    }
}</code></pre>

<!-- ==================================================================== -->
<h2 id="excecoes">13. Exceções</h2>

<table>
    <thead><tr><th>Exceção</th><th>Quando Usar</th><th>Exemplo</th></tr></thead>
    <tbody>
        <tr>
            <td><code>ApiException</code></td>
            <td>Erros de regra de negócio (não encontrado, validação, etc.)</td>
            <td><code>throw new ApiException(msg: 'Produto indisponível.', code: 422);</code></td>
        </tr>
        <tr>
            <td><code>AlertaException</code></td>
            <td>Falhas de infraestrutura que precisam de notificação</td>
            <td><code>throw new AlertaException(msg: 'Serviço fora do ar', nivel: 'critical');</code></td>
        </tr>
    </tbody>
</table>

<p>O <code>HandlerExceptionCritical</code> trata exceções de infraestrutura (banco offline, Redis fora) com throttle de 5 minutos para evitar flood de alertas.</p>

<!-- ==================================================================== -->
<h2 id="jwt">14. Autenticação JWT</h2>

<p>Autenticação via JWT usando <code>php-open-source-saver/jwt-auth</code>. Middleware <code>jwt.verify</code> valida tokens nas rotas protegidas.</p>

<table>
    <thead><tr><th>Endpoint</th><th>Método</th><th>Autenticação</th><th>Descrição</th></tr></thead>
    <tbody>
        <tr>
            <td><code>/api/v1/login</code></td>
            <td><span class="badge badge-post">POST</span></td>
            <td>Pública</td>
            <td>Login — retorna JWT</td>
        </tr>
        <tr>
            <td><code>/api/v1/me</code></td>
            <td><span class="badge badge-get">GET</span></td>
            <td>JWT</td>
            <td>Dados do usuário logado</td>
        </tr>
        <tr>
            <td><code>/api/v1/refresh</code></td>
            <td><span class="badge badge-post">POST</span></td>
            <td>JWT</td>
            <td>Renova o token</td>
        </tr>
    </tbody>
</table>

<h4>Header de autenticação</h4>
<pre><code>Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...</code></pre>

<div class="callout callout-info">
    <strong>🔐 RBAC no payload JWT:</strong> O payload do token inclui as roles do usuário via <code>getJWTCustomClaims()</code>, mas as permissões detalhadas são buscadas via cache Redis para evitar token bloat.
    Veja seção <a href="#rbac">RBAC (seção 15)</a> para detalhes.
</div>

<!-- ==================================================================== -->
<h2 id="rbac">15. RBAC — Controle de Acesso</h2>

<p>Permissões no formato <code>modulo.acao</code>. Cacheadas no Redis por 15 minutos. Integradas ao JWT claims.</p>

<h3>Permissões disponíveis (exemplos)</h3>
<pre><code>clientes.ver       clientes.criar     clientes.editar    clientes.excluir
produtos.ver       produtos.criar     produtos.editar    produtos.excluir
vendas.ver         vendas.criar       vendas.cancelar
caixa.abrir        caixa.fechar       caixa.ver
relatorio.financeiro   relatorio.fiscal
admin.tudo         <span class="cm">← permissão mestre (bypass de todas)</span></code></pre>

<h3>Aplicando nas Rotas</h3>
<pre><code><span class="cm">// routes/api.php</span>
Route::<span class="fn">group</span>([<span class="str">'middleware'</span> => [<span class="str">'jwt.verify'</span>]], <span class="kw">function</span> (): <span class="tp">void</span> {
    Route::<span class="fn">get</span>(<span class="str">'/produtos'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'index'</span>]);

    Route::<span class="fn">post</span>(<span class="str">'/produtos'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'store'</span>])
        -><span class="fn">middleware</span>(<span class="str">'permission:produtos.criar'</span>);

    Route::<span class="fn">delete</span>(<span class="str">'/produtos/{id}'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'destroy'</span>])
        -><span class="fn">middleware</span>(<span class="str">'permission:produtos.excluir'</span>);

    Route::<span class="fn">get</span>(<span class="str">'/relatorios/financeiro'</span>, [RelatorioController::<span class="kw">class</span>, <span class="str">'financeiro'</span>])
        -><span class="fn">middleware</span>([<span class="str">'permission:relatorio.financeiro'</span>, <span class="str">'throttle:api-reports'</span>]);
});</code></pre>

<h3>Verificando no Service</h3>
<pre><code><span class="var">$usuario</span> = <span class="var">$this</span>-><span class="var">usuarioLogado</span>-><span class="fn">getUser</span>();

<span class="kw">if</span> (!<span class="var">$usuario</span>-><span class="fn">hasPermission</span>(<span class="str">'vendas.cancelar'</span>)) {
    <span class="kw">throw new</span> <span class="tp">ApiException</span>(
        msg: <span class="str">'Sem permissão para cancelar vendas.'</span>,
        code: Response::<span class="var">HTTP_FORBIDDEN</span>,
    );
}

<span class="cm">// Após alterar roles — invalidar cache de permissões</span>
<span class="var">$usuario</span>-><span class="fn">invalidatePermissionsCache</span>();</code></pre>

<h3>Roles disponíveis</h3>
<table>
    <thead><tr><th>Role</th><th>Descrição</th></tr></thead>
    <tbody>
        <tr><td><code>admin</code></td><td>Acesso total (<code>admin.tudo</code>)</td></tr>
        <tr><td><code>gerente</code></td><td>Relatórios + vendas + cadastros</td></tr>
        <tr><td><code>vendedor</code></td><td>Vendas + visualização de produtos</td></tr>
        <tr><td><code>caixa</code></td><td>Operação de caixa</td></tr>
        <tr><td><code>tecnico</code></td><td>Assistência técnica</td></tr>
        <tr><td><code>financeiro</code></td><td>Relatórios financeiros + contas</td></tr>
    </tbody>
</table>

<!-- ==================================================================== -->
<h2 id="rotas">16. Rotas da API</h2>

<p>Todas as rotas ficam em <code>routes/api.php</code> sob o prefixo <code>/api/v1</code>.</p>

<pre><code><span class="cm">// routes/api.php</span>
Route::<span class="fn">group</span>([<span class="str">'prefix'</span> => <span class="str">'v1'</span>], <span class="kw">function</span> (): <span class="tp">void</span> {

    <span class="cm">// Rotas públicas</span>
    Route::<span class="fn">post</span>(<span class="str">'/login'</span>, [AuthController::<span class="kw">class</span>, <span class="str">'login'</span>]);

    <span class="cm">// Rotas protegidas</span>
    Route::<span class="fn">group</span>([<span class="str">'middleware'</span> => [<span class="str">'jwt.verify'</span>]], <span class="kw">function</span> (): <span class="tp">void</span> {
        Route::<span class="fn">get</span>(<span class="str">'/me'</span>, [AuthController::<span class="kw">class</span>, <span class="str">'me'</span>]);
        Route::<span class="fn">post</span>(<span class="str">'/refresh'</span>, [AuthController::<span class="kw">class</span>, <span class="str">'refresh'</span>]);

        <span class="cm">// Grupo de domínio — exemplo:</span>
        Route::<span class="fn">group</span>([<span class="str">'prefix'</span> => <span class="str">'produtos'</span>], <span class="kw">function</span> (): <span class="tp">void</span> {
            Route::<span class="fn">get</span>(<span class="str">'/'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'index'</span>])
                -><span class="fn">middleware</span>(<span class="str">'permission:produtos.ver'</span>);
            Route::<span class="fn">get</span>(<span class="str">'/{id}'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'show'</span>])
                -><span class="fn">middleware</span>(<span class="str">'permission:produtos.ver'</span>);
            Route::<span class="fn">post</span>(<span class="str">'/'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'store'</span>])
                -><span class="fn">middleware</span>([<span class="str">'permission:produtos.criar'</span>, <span class="str">'throttle:api-write'</span>]);
            Route::<span class="fn">put</span>(<span class="str">'/{id}'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'update'</span>])
                -><span class="fn">middleware</span>([<span class="str">'permission:produtos.editar'</span>, <span class="str">'throttle:api-write'</span>]);
            Route::<span class="fn">delete</span>(<span class="str">'/{id}'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'destroy'</span>])
                -><span class="fn">middleware</span>(<span class="str">'permission:produtos.excluir'</span>);
        });
    });
});</code></pre>

<!-- ==================================================================== -->
<h2 id="swagger">17. Swagger / OpenAPI</h2>

<table>
    <thead><tr><th>URL</th><th>Descrição</th></tr></thead>
    <tbody>
        <tr>
            <td><a href="/docs/api"><code>/docs/api</code></a></td>
            <td>🚀 <strong>Scalar</strong> — UI moderna com layout 3 colunas (recomendado)</td>
        </tr>
        <tr>
            <td><a href="/api/documentation"><code>/api/documentation</code></a></td>
            <td>📋 Swagger UI clássico</td>
        </tr>
        <tr>
            <td><a href="/docs/openapi.json"><code>/docs/openapi.json</code></a></td>
            <td>📄 JSON da especificação OpenAPI</td>
        </tr>
    </tbody>
</table>

<h4>Regenerar a documentação</h4>
<pre><code>php artisan l5-swagger:generate</code></pre>

<h4>Schemas reutilizáveis (definidos em OpenApiSpec.php)</h4>
<table>
    <thead><tr><th>Schema</th><th>Uso</th></tr></thead>
    <tbody>
        <tr><td><code>RespostaPadrao</code></td><td>Resposta de sucesso (conteudo, msg, code)</td></tr>
        <tr><td><code>RespostaErro</code></td><td>Resposta de erro em produção</td></tr>
        <tr><td><code>RespostaErroDetalhado</code></td><td>Resposta de erro em desenvolvimento (com trace)</td></tr>
        <tr><td><code>TokenJWT</code></td><td>Resposta do login (access_token, token_type, expires_in)</td></tr>
    </tbody>
</table>

<h4>Anotações — Sempre usar PHP Attributes</h4>
<pre><code><span class="kw">use</span> <span class="tp">OpenApi\Attributes</span> <span class="kw">as</span> <span class="tp">OA</span>;

<span class="cm">// ✅ CORRETO — PHP 8+ Attributes</span>
<span class="at">#[OA\Get(</span>
    <span class="var">path</span>: <span class="str">'/produtos'</span>,
    <span class="var">tags</span>: [<span class="str">'Produto'</span>],
    <span class="var">security</span>: [[<span class="str">'bearerAuth'</span> => []]],
<span class="at">)]</span>

<span class="cm">// ❌ ERRADO — Não usar docblock annotations</span>
<span class="cm">// @OA\Get(path="/produtos")</span></code></pre>

<!-- ==================================================================== -->
<h2 id="cache">18. Cache com Redis</h2>

<p>Driver padrão é <strong>Redis</strong>. TTLs configurados por domínio em <code>config/cache.php</code>.</p>

<table>
    <thead><tr><th>Domínio</th><th>TTL</th><th>Motivo</th></tr></thead>
    <tbody>
        <tr><td><code>caixa</code></td><td>60s</td><td>Dados em tempo real</td></tr>
        <tr><td><code>relatorios</code></td><td>5 min</td><td>Relatórios frequentes</td></tr>
        <tr><td><code>clientes</code></td><td>30 min</td><td>Cadastros</td></tr>
        <tr><td><code>precos</code></td><td>15 min</td><td>Sensível a promoções</td></tr>
        <tr><td><code>produtos</code></td><td>1 hora</td><td>Catálogo</td></tr>
        <tr><td><code>configuracoes</code></td><td>24 hrs</td><td>Configurações do sistema</td></tr>
    </tbody>
</table>

<h3>CacheService — uso no Service</h3>
<pre><code><span class="kw">use</span> <span class="tp">App\Services\Cache\CacheService</span>;

<span class="kw">class</span> <span class="tp">ProdutoService</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private readonly</span> <span class="tp">ProdutoRepositoryInterface</span> <span class="var">$repository</span>,
        <span class="kw">private readonly</span> <span class="tp">CacheService</span> <span class="var">$cache</span>,
    ) {}

    <span class="kw">public function</span> <span class="fn">buscarPorId</span>(<span class="tp">int</span> <span class="var">$id</span>): <span class="tp">object</span>
    {
        <span class="kw">return</span> <span class="var">$this</span>-><span class="var">cache</span>-><span class="fn">remember</span>(
            key: <span class="str">"produto:{$id}"</span>,
            callback: <span class="kw">fn</span>() => <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">find</span>(<span class="var">$id</span>),
            ttl: <span class="fn">config</span>(<span class="str">'cache.ttl.produtos'</span>),
        );
    }

    <span class="kw">public function</span> <span class="fn">criar</span>(<span class="tp">array</span> <span class="var">$data</span>): <span class="tp">object</span>
    {
        <span class="var">$produto</span> = <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">create</span>(<span class="var">$data</span>);
        <span class="var">$this</span>-><span class="var">cache</span>-><span class="fn">forgetByTag</span>(<span class="str">'produtos'</span>); <span class="cm">// invalida listagem</span>
        <span class="kw">return</span> <span class="var">$produto</span>;
    }
}</code></pre>

<h3>CacheableRepository — cache automático no Repository</h3>
<pre><code><span class="kw">use</span> <span class="tp">App\Repository\Traits\CacheableRepository</span>;

<span class="kw">class</span> <span class="tp">ProdutoEloquentRepository</span> <span class="kw">extends</span> <span class="tp">EloquentRepository</span>
{
    <span class="kw">use</span> <span class="tp">CacheableRepository</span>;

    <span class="kw">protected int</span>   <span class="var">$cacheTtl</span>  = <span class="num">3600</span>;
    <span class="kw">protected array</span> <span class="var">$cacheTags</span> = [<span class="str">'produtos'</span>, <span class="str">'catalogo'</span>];
}

<span class="cm">// find(), findAll(), paginate() → cacheados automaticamente</span>
<span class="cm">// create(), update(), delete() → invalidam o cache automaticamente</span>
<span class="cm">// Desabilitar cache para uma query específica:</span>
<span class="var">$produtos</span> = <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">withoutCache</span>()-><span class="fn">findAll</span>();</code></pre>

<!-- ==================================================================== -->
<h2 id="fiscal">19. Calculadora Fiscal</h2>

<p>O <code>FiscalCalculatorService</code> centraliza todos os cálculos fiscais do ERP usando BCMath e o padrão ABNT NBR 5891.</p>

<pre><code><span class="kw">use</span> <span class="tp">App\Services\Fiscal\FiscalCalculatorService</span>;
<span class="kw">use</span> <span class="tp">App\ValueObjects\Money</span>;
<span class="kw">use</span> <span class="tp">App\ValueObjects\Percentage</span>;
<span class="kw">use</span> <span class="tp">App\ValueObjects\Quantity</span>;

<span class="kw">class</span> <span class="tp">VendaService</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private readonly</span> <span class="tp">FiscalCalculatorService</span> <span class="var">$fiscal</span>,
    ) {}

    <span class="kw">public function</span> <span class="fn">calcularItem</span>(<span class="tp">array</span> <span class="var">$item</span>): <span class="tp">Money</span>
    {
        <span class="var">$preco</span>    = Money::<span class="fn">of</span>(<span class="var">$item</span>[<span class="str">'preco'</span>]);
        <span class="var">$desconto</span> = Percentage::<span class="fn">of</span>(<span class="var">$item</span>[<span class="str">'desconto'</span>]);
        <span class="var">$qtd</span>      = Quantity::<span class="fn">of</span>(<span class="var">$item</span>[<span class="str">'quantidade'</span>]);
        <span class="var">$icms</span>     = Percentage::<span class="fn">of</span>(<span class="var">$item</span>[<span class="str">'aliquota_icms'</span>]);

        <span class="var">$precoLiquido</span> = <span class="var">$this</span>-><span class="var">fiscal</span>-><span class="fn">applyDiscount</span>(price: <span class="var">$preco</span>, discount: <span class="var">$desconto</span>);
        <span class="var">$totalItem</span>    = <span class="var">$this</span>-><span class="var">fiscal</span>-><span class="fn">calculateTotal</span>(quantity: <span class="var">$qtd</span>, unitPrice: <span class="var">$precoLiquido</span>);
        <span class="var">$valorIcms</span>    = <span class="var">$this</span>-><span class="var">fiscal</span>-><span class="fn">calculateIcmsInternal</span>(baseCalculo: <span class="var">$totalItem</span>, aliquota: <span class="var">$icms</span>);

        <span class="kw">return</span> <span class="var">$totalItem</span>;
    }

    <span class="kw">public function</span> <span class="fn">somarItens</span>(<span class="tp">array</span> <span class="var">$itens</span>): <span class="tp">Money</span>
    {
        <span class="cm">// Soma com BCMath — sem acumulação de erros de float</span>
        <span class="kw">return</span> <span class="var">$this</span>-><span class="var">fiscal</span>-><span class="fn">sumItems</span>(
            items: <span class="fn">array_map</span>(<span class="kw">fn</span>(<span class="var">$i</span>) => <span class="var">$this</span>-><span class="fn">calcularItem</span>(<span class="var">$i</span>), <span class="var">$itens</span>),
        );
    }

    <span class="kw">public function</span> <span class="fn">ratearFrete</span>(<span class="tp">Money</span> <span class="var">$frete</span>, <span class="tp">array</span> <span class="var">$totais</span>): <span class="tp">array</span>
    {
        <span class="cm">// Rateio proporcional — garante que soma dos rateios = frete original</span>
        <span class="kw">return</span> <span class="var">$this</span>-><span class="var">fiscal</span>-><span class="fn">proRata</span>(total: <span class="var">$frete</span>, weights: <span class="var">$totais</span>);
    }
}</code></pre>

<h3>Modos de arredondamento (RoundingService)</h3>
<table>
    <thead><tr><th>Modo</th><th>Exemplo (2 decimais)</th><th>Uso</th></tr></thead>
    <tbody>
        <tr><td><code>halfUp</code></td><td>2.345 → 2.35</td><td>NF-e, fiscal (ABNT NBR 5891) ✅ padrão</td></tr>
        <tr><td><code>halfDown</code></td><td>2.345 → 2.34</td><td>Uso específico</td></tr>
        <tr><td><code>halfEven</code></td><td>2.345 → 2.34, 2.355 → 2.36</td><td>Contabilidade (Banker's)</td></tr>
        <tr><td><code>truncate</code></td><td>2.349 → 2.34</td><td>Corte simples sem arredondar</td></tr>
    </tbody>
</table>

<!-- ==================================================================== -->
<h2 id="events">20. Domain Events</h2>

<p>Domain Events permitem comunicação entre domínios sem acoplamento direto, usando o EventDispatcher nativo do Laravel.</p>

<pre><code><span class="cm">// 1. Criar o Event</span>
<span class="cm">// app/Events/Domain/Venda/VendaRealizadaEvent.php</span>
<span class="kw">class</span> <span class="tp">VendaRealizadaEvent</span> <span class="kw">extends</span> <span class="tp">DomainEvent</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">public readonly</span> <span class="tp">int</span>   <span class="var">$vendaId</span>,
        <span class="kw">public readonly</span> <span class="tp">int</span>   <span class="var">$clienteId</span>,
        <span class="kw">public readonly</span> <span class="tp">Money</span> <span class="var">$totalVenda</span>,
    ) {
        <span class="kw">parent</span>::<span class="fn">__construct</span>();
    }
}

<span class="cm">// 2. Disparar no Service — DENTRO da transaction</span>
<span class="kw">public function</span> <span class="fn">finalizar</span>(<span class="tp">int</span> <span class="var">$pedidoId</span>): <span class="tp">object</span>
{
    <span class="kw">return</span> <span class="var">$this</span>-><span class="fn">transaction</span>(<span class="kw">function</span> () <span class="kw">use</span> (<span class="var">$pedidoId</span>) {
        <span class="var">$venda</span> = <span class="var">$this</span>-><span class="var">repository</span>-><span class="fn">create</span>([<span class="cm">/* ... */</span>]);
        <span class="fn">event</span>(<span class="kw">new</span> <span class="tp">VendaRealizadaEvent</span>(
            vendaId: <span class="var">$venda</span>-><span class="var">id</span>,
            clienteId: <span class="var">$venda</span>-><span class="var">cliente_id</span>,
            totalVenda: <span class="var">$venda</span>-><span class="var">total</span>,
        ));
        <span class="kw">return</span> <span class="var">$venda</span>;
    }); <span class="cm">// rollback cancela o event em caso de falha</span>
}

<span class="cm">// 3. Registrar Listeners em EventServiceProvider</span>
<span class="kw">protected</span> <span class="var">$listen</span> = [
    <span class="tp">VendaRealizadaEvent</span>::<span class="kw">class</span> => [
        <span class="tp">AtualizarEstoqueListener</span>::<span class="kw">class</span>,
        <span class="tp">GerarComissaoListener</span>::<span class="kw">class</span>,
        <span class="tp">NotificarClienteListener</span>::<span class="kw">class</span>,
    ],
];</code></pre>

<!-- ==================================================================== -->
<h2 id="audit">21. Audit Trail</h2>

<p>A trait <code>Auditable</code> registra automaticamente quem criou, alterou ou excluiu qualquer registro.</p>

<pre><code><span class="kw">use</span> <span class="tp">App\Models\Traits\Auditable</span>;

<span class="kw">class</span> <span class="tp">Produto</span> <span class="kw">extends</span> <span class="tp">Model</span>
{
    <span class="kw">use</span> <span class="tp">Auditable</span>;

    <span class="cm">// Campos excluídos do log (senhas, tokens)</span>
    <span class="kw">protected array</span> <span class="var">$auditExclude</span> = [<span class="str">'senha'</span>, <span class="str">'token_reset'</span>];
}</code></pre>

<h4>O que é registrado na tabela <code>webc_auditoria</code>:</h4>
<table>
    <thead><tr><th>Campo</th><th>Descrição</th></tr></thead>
    <tbody>
        <tr><td><code>acao</code></td><td><code>criado</code>, <code>atualizado</code>, <code>deletado</code></td></tr>
        <tr><td><code>tabela</code></td><td>Nome da tabela auditada</td></tr>
        <tr><td><code>registro_id</code></td><td>ID do registro afetado</td></tr>
        <tr><td><code>usuario_id</code></td><td>ID do usuário (do JWT)</td></tr>
        <tr><td><code>payload_antes</code></td><td>JSON com valores ANTES da alteração</td></tr>
        <tr><td><code>payload_depois</code></td><td>JSON com valores DEPOIS da alteração</td></tr>
        <tr><td><code>ip</code></td><td>IP da requisição</td></tr>
        <tr><td><code>criado_em</code></td><td>Timestamp UTC da ação</td></tr>
    </tbody>
</table>

<div class="callout callout-danger">
    <strong>🔒 Segurança:</strong> Nunca retorne dados da <code>webc_auditoria</code> diretamente na API pública. Estes dados são para uso interno/administrativo apenas.
</div>

<!-- ==================================================================== -->
<h2 id="transactions">22. Gerenciamento de Transações</h2>

<p>A trait <code>WithTransaction</code> padroniza o uso de transações nos Services.</p>

<pre><code><span class="kw">use</span> <span class="tp">App\Services\Traits\WithTransaction</span>;

<span class="kw">class</span> <span class="tp">VendaService</span>
{
    <span class="kw">use</span> <span class="tp">WithTransaction</span>;

    <span class="kw">public function</span> <span class="fn">criar</span>(<span class="tp">array</span> <span class="var">$dados</span>): <span class="tp">object</span>
    {
        <span class="cm">// Tudo dentro do closure é atômico</span>
        <span class="kw">return</span> <span class="var">$this</span>-><span class="fn">transaction</span>(<span class="kw">function</span> () <span class="kw">use</span> (<span class="var">$dados</span>) {
            <span class="var">$venda</span>   = <span class="var">$this</span>-><span class="var">vendaRepo</span>-><span class="fn">create</span>(<span class="var">$dados</span>);
            <span class="var">$this</span>-><span class="var">estoqueRepo</span>-><span class="fn">baixar</span>(<span class="var">$dados</span>[<span class="str">'itens'</span>]);
            <span class="var">$this</span>-><span class="var">financeiroRepo</span>-><span class="fn">lancarCredito</span>(<span class="var">$venda</span>);
            <span class="fn">event</span>(<span class="kw">new</span> <span class="tp">VendaRealizadaEvent</span>(<span class="var">$venda</span>-><span class="var">id</span>, <span class="cm">/* ... */</span>));
            <span class="kw">return</span> <span class="var">$venda</span>;
        }); <span class="cm">// qualquer falha → rollback de tudo</span>
    }

    <span class="cm">// Retry automático para deadlocks MySQL</span>
    <span class="kw">public function</span> <span class="fn">criarComRetry</span>(<span class="tp">array</span> <span class="var">$dados</span>): <span class="tp">object</span>
    {
        <span class="kw">return</span> <span class="var">$this</span>-><span class="fn">transactionWithRetry</span>(
            callback: <span class="kw">fn</span>() => <span class="var">$this</span>-><span class="var">vendaRepo</span>-><span class="fn">create</span>(<span class="var">$dados</span>),
            attempts: <span class="num">3</span>,
        );
    }
}</code></pre>

<table>
    <thead><tr><th>Situação</th><th>Transação?</th></tr></thead>
    <tbody>
        <tr><td>Simples <code>find()</code> ou <code>paginate()</code></td><td>❌ Desnecessária</td></tr>
        <tr><td>Operação em 1 tabela simples</td><td>❌ Opcional</td></tr>
        <tr><td>Venda (venda + estoque + financeiro)</td><td>✅ Obrigatória</td></tr>
        <tr><td>Fechamento de caixa</td><td>✅ Obrigatória</td></tr>
        <tr><td>Locação (reserva + contrato + cobrança)</td><td>✅ Obrigatória</td></tr>
    </tbody>
</table>

<!-- ==================================================================== -->
<h2 id="ratelimit">23. Rate Limiting &amp; Filas</h2>

<h3>Rate Limiters configurados</h3>
<table>
    <thead><tr><th>Limiter</th><th>Limite</th><th>Uso</th></tr></thead>
    <tbody>
        <tr><td><code>api-read</code></td><td>100/min</td><td>Consultas, listagens</td></tr>
        <tr><td><code>api-write</code></td><td>30/min</td><td>Criações, atualizações, exclusões</td></tr>
        <tr><td><code>api-reports</code></td><td>5/min</td><td>Geração de relatórios</td></tr>
    </tbody>
</table>

<pre><code><span class="cm">// Aplicando nas rotas</span>
Route::<span class="fn">post</span>(<span class="str">'/produtos'</span>, [ProdutoController::<span class="kw">class</span>, <span class="str">'store'</span>])
    -><span class="fn">middleware</span>(<span class="str">'throttle:api-write'</span>);

Route::<span class="fn">get</span>(<span class="str">'/relatorios/financeiro'</span>, [RelatorioController::<span class="kw">class</span>, <span class="str">'financeiro'</span>])
    -><span class="fn">middleware</span>([<span class="str">'permission:relatorio.financeiro'</span>, <span class="str">'throttle:api-reports'</span>]);</code></pre>

<h3>Filas Redis para operações pesadas</h3>
<table>
    <thead><tr><th>Fila</th><th>Descrição</th><th>Timeout</th><th>Tries</th></tr></thead>
    <tbody>
        <tr><td><code>default</code></td><td>Operações gerais</td><td>60s</td><td>3</td></tr>
        <tr><td><code>relatorios</code></td><td>Geração de relatórios PDF</td><td>600s</td><td>1</td></tr>
        <tr><td><code>emails</code></td><td>E-mails e notificações</td><td>60s</td><td>3</td></tr>
        <tr><td><code>fiscal</code></td><td>NF-e, NFC-e, SPED</td><td>300s</td><td>2</td></tr>
    </tbody>
</table>

<pre><code><span class="cm">// Despachando um Job</span>
GerarRelatorioFinanceiroJob::<span class="fn">dispatch</span>(<span class="var">$params</span>)-><span class="fn">onQueue</span>(<span class="str">'relatorios'</span>);

<span class="cm">// Iniciando workers (cada um em processo separado)</span>
php artisan queue:work redis --queue=relatorios --timeout=600
php artisan queue:work redis --queue=fiscal --timeout=300
php artisan queue:work redis --queue=emails</code></pre>

<!-- ==================================================================== -->
<h2 id="comandos">24. Comandos Artisan Customizados</h2>

<table>
    <thead><tr><th>Comando</th><th>Gera</th><th>Exemplo</th></tr></thead>
    <tbody>
        <tr>
            <td><code>make:domain</code></td>
            <td>Model + DTO + Repository + Service + Controller + Swagger Tag + Binding</td>
            <td><code>php artisan make:domain Produto --table=webc_produto</code></td>
        </tr>
        <tr>
            <td><code>make:domain ... --datatables</code></td>
            <td>Tudo acima + HasDataTables no Repository</td>
            <td><code>php artisan make:domain Produto --datatables</code></td>
        </tr>
        <tr>
            <td><code>make:repository</code></td>
            <td>Interface + Eloquent + Binding no BindsRepositorios</td>
            <td><code>php artisan make:repository Produto</code></td>
        </tr>
        <tr>
            <td><code>make:repository ... --datatables</code></td>
            <td>Repository com HasDataTables + DataTablesInterface</td>
            <td><code>php artisan make:repository Produto --datatables</code></td>
        </tr>
        <tr>
            <td><code>make:service</code></td>
            <td>Service com injeção de Repository + UsuarioLogado + Converter</td>
            <td><code>php artisan make:service Produto</code></td>
        </tr>
        <tr>
            <td><code>make:dto</code></td>
            <td>DTO readonly com OA\Schema + RequestBodyConverterInterface</td>
            <td><code>php artisan make:dto Produto</code></td>
        </tr>
    </tbody>
</table>

<div class="callout callout-success">
    <strong>💡 Dica:</strong> Todos os arquivos gerados já incluem <code>@author Rafael Rozgrin</code>, PHPDoc completo e anotações Swagger quando aplicável.
</div>

<!-- ==================================================================== -->
<h2 id="hierarquia">25. Hierarquia de Pastas nos Comandos</h2>

<p>Os comandos <code>make:*</code> suportam hierarquia de pastas via separador <code>\</code>. O último segmento é o nome da entidade; os anteriores viram diretórios.</p>

<table>
    <thead><tr><th>Comando</th><th>Diretórios Criados</th><th>Namespace</th></tr></thead>
    <tbody>
        <tr>
            <td><code>make:repository Produto</code></td>
            <td><code>Repository/.../Produto/</code></td>
            <td><code>App\...\Produto</code></td>
        </tr>
        <tr>
            <td><code>make:repository BaseErp\\Produtos\\Produto</code></td>
            <td><code>Repository/.../BaseErp/Produtos/</code></td>
            <td><code>App\...\BaseErp\Produtos</code></td>
        </tr>
    </tbody>
</table>

<h4>Exemplo visual</h4>
<pre><code><span class="cm"># Comando:</span>
php artisan make:domain BaseErp\\Cadastros\\Cliente --table=webc_cliente

<span class="cm"># Arquivos gerados:</span>
app/Models/BaseErp/Cadastros/Cliente.php
app/DTO/BaseErp/Cadastros/ClienteDTO.php
app/Repository/Contracts/Models/BaseErp/Cadastros/ClienteRepositoryInterface.php
app/Repository/Eloquent/Models/BaseErp/Cadastros/ClienteEloquentRepository.php
app/Services/BaseErp/Cadastros/ClienteService.php
app/Http/Controllers/BaseErp/Cadastros/ClienteController.php</code></pre>

<!-- ==================================================================== -->
<h2 id="convencoes">26. Convenções de Código</h2>

<table>
    <thead><tr><th>Regra</th><th>Exemplo</th></tr></thead>
    <tbody>
        <tr>
            <td><code>declare(strict_types=1)</code> — sempre</td>
            <td>Topo de cada arquivo PHP</td>
        </tr>
        <tr>
            <td>Typed properties — sempre</td>
            <td><code>private readonly ProdutoService $service</code></td>
        </tr>
        <tr>
            <td>Constructor promotion — sempre</td>
            <td><code>__construct(private readonly X $x)</code></td>
        </tr>
        <tr>
            <td>Named arguments — 2+ parâmetros</td>
            <td><code>$this->send(conteudo: $data, code: 200)</code></td>
        </tr>
        <tr>
            <td>Match expressions (não switch)</td>
            <td><code>match($status) { 'A' => 'Ativo', ... }</code></td>
        </tr>
        <tr>
            <td><code>config()</code> — nunca <code>env()</code> em app code</td>
            <td><code>config('app.env')</code> ✅ <code>env('APP_ENV')</code> ❌</td>
        </tr>
        <tr>
            <td>PHPDoc em português</td>
            <td><code>/** Busca um produto pelo ID. */</code></td>
        </tr>
        <tr>
            <td><code>@author</code> obrigatório</td>
            <td><code>@author Rafael Rozgrin &lt;rrozgrin@gmail.com&gt;</code></td>
        </tr>
        <tr>
            <td>Readonly para injeções</td>
            <td><code>private readonly Service $service</code></td>
        </tr>
        <tr>
            <td>Trailing comma — sempre</td>
            <td>Parâmetros, arrays, enums</td>
        </tr>
    </tbody>
</table>

<!-- ==================================================================== -->
<h2 id="checklist">27. Checklist — Criando um Novo Domínio</h2>

<p>Siga estes passos para criar um novo domínio completo:</p>

<ol class="checklist">
    <li>Execute <code>php artisan make:domain NomeEntidade --table=webc_tabela</code> (ou <code>--datatables</code> se necessário)</li>
    <li>Edite o <strong>Model</strong> — configure <code>$fillable</code>, <code>casts()</code> e relacionamentos
        <ul style="margin-top:6px;padding-left:20px;color:#8892b0">
            <li>Colunas monetárias → <code>MoneyCast::class</code> (nunca <code>'decimal:2'</code>)</li>
            <li>CPF/CNPJ → <code>CpfCast::class</code> / <code>CnpjCast::class</code></li>
            <li>Se precisa de rastreabilidade → <code>use Auditable</code></li>
        </ul>
    </li>
    <li>Edite o <strong>DTO</strong> — adicione as propriedades com <code>#[OA\Property]</code> (campos monetários como <code>string</code>)</li>
    <li>Edite a <strong>Interface</strong> do Repository — adicione métodos específicos do domínio</li>
    <li>Implemente os métodos no <strong>Eloquent Repository</strong> (adicione <code>use CacheableRepository</code> se necessário)</li>
    <li>Edite o <strong>Service</strong> — descomente <code>criar()</code>, <code>atualizar()</code>, <code>remover()</code> e mapeie os campos
        <ul style="margin-top:6px;padding-left:20px;color:#8892b0">
            <li>Operações multi-tabela → use <code>$this->transaction(fn() => ...)</code></li>
            <li>Cálculos fiscais → use <code>FiscalCalculatorService</code></li>
            <li>Após gravar → dispare o <code>DomainEvent</code> correspondente</li>
        </ul>
    </li>
    <li>Defina as <strong>permissões RBAC</strong> necessárias (ex: <code>produto.ver</code>, <code>produto.editar</code>)</li>
    <li>Edite o <strong>Controller</strong> — descomente as rotas <code>store</code>, <code>update</code>, <code>destroy</code> e suas anotações Swagger</li>
    <li>Adicione as <strong>rotas</strong> em <code>routes/api.php</code> dentro do grupo <code>jwt.verify</code> com <code>middleware('permission:...')</code></li>
    <li>Execute <code>php artisan l5-swagger:generate</code> para atualizar a documentação</li>
    <li>Verifique em <code>/docs/api</code> se os endpoints aparecem corretamente</li>
    <li>Escreva <strong>testes</strong> para o Service, ValueObjects e Calculator utilizados</li>
</ol>

<div class="callout callout-warning">
    <strong>⚠️ Lembrete:</strong> O comando <code>make:domain</code> já registra a tag Swagger automaticamente em <code>OpenApiSpec.php</code>, adiciona o binding no <code>BindsRepositorios.php</code> e exibe a sugestão de rotas no terminal.
</div>


<!-- ==================================================================== -->
<div class="footer">
    API Generica v3 — Laravel 13 / PHP 8.5+ — {{ date('Y') }}<br>
    Arquitetura idealizada por <strong>Rafael Rozgrin</strong>
</div>

</main>
</body>
</html>