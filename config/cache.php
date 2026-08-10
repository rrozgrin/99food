<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | IMPORTANTE: Em produção, use Redis como driver de cache.
    | Configure CACHE_STORE=redis no .env de produção.
    | Redis oferece cache com tags (invalidação por domínio) e performance
    | muito superior ao banco de dados para um ERP com 4k+ clientes.
    |--------------------------------------------------------------------------
    */
    'default' => env('CACHE_STORE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION', 'mysql_marketplace'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION', 'mysql_marketplace'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | TTLs por Domínio (em segundos)
    |--------------------------------------------------------------------------
    |
    | Define o tempo de vida do cache para cada domínio do sistema ERP.
    | Esses valores são utilizados pelo CacheService e CacheableRepository.
    | Ajuste conforme a volatilidade dos dados de cada módulo.
    |
    */
    'ttl' => [
        'configuracoes' => 86400,  // 24 horas  — dados raramente alterados
        'produtos' => 3600,   // 1 hora    — catálogo e preços
        'clientes' => 1800,   // 30 minutos
        'fornecedores' => 1800,   // 30 minutos
        'funcionarios' => 3600,   // 1 hora
        'categorias' => 7200,   // 2 horas
        'formas_pagamento' => 3600, // 1 hora
        'vendas' => 300,    // 5 minutos — alta volatilidade
        'pedidos' => 300,    // 5 minutos
        'caixa' => 60,     // 1 minuto  — dados em tempo real
        'financeiro' => 300,    // 5 minutos
        'estoque' => 120,    // 2 minutos — crítico para consistência
        'relatorios' => 300,    // 5 minutos — relatórios são pesados
        'default' => 600,    // 10 minutos — fallback genérico
    ],

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    */

    'serializable_classes' => false,

];
