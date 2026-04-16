<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentação do ecossistema para desenvolvedores
Route::get('/docs/ecossistema', function () {
    return view('docs.ecossistema');
})->name('docs.ecossistema');

// Documentação da API — Scalar (layout moderno 3 colunas)
Route::get('/docs/api', function () {
    return view('docs.api-reference');
})->name('docs.api');

// JSON OpenAPI para consumo do Scalar (servido direto do storage)
Route::get('/docs/openapi.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (! file_exists($path)) {
        abort(404, 'Execute: php artisan l5-swagger:generate');
    }

    return response()->file($path, ['Content-Type' => 'application/json']);
})->name('docs.openapi');
