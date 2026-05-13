<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Hub API — Documentação</title>
    <meta name="description" content="Documentação interativa da Integration Hub API com layout moderno de 3 colunas.">

    <style>
        /* Customizações de branding da Integration Hub API */
        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>

    <!--
    |----------------------------------------------------------------------
    | Scalar API Reference — Layout moderno 3 colunas
    |----------------------------------------------------------------------
    |
    | Colunas:
    |   📑 Esquerda  → Índice / navegação dos endpoints
    |   📋 Centro    → Estrutura do endpoint (params, body, descrição)
    |   💻 Direita   → Exemplos de request/response JSON + códigos de erro
    |
    | O JSON é gerado pelo l5-swagger em /docs/api-docs.json
    |
    | @author Rafael Rozgrin <rrozgrin@gmail.com>
    |
    -->
    <script
        id="api-reference"
        data-url="{{ url('/docs/openapi.json') }}"
        data-configuration='{
            "theme": "kepler",
            "layout": "modern",
            "defaultHttpClient": {
                "targetKey": "php",
                "clientKey": "guzzle"
            },
            "hiddenClients": ["c", "clojure", "ocaml", "powershell", "objc", "r"],
            "metaData": {
                "title": "Integration Hub API — Documentação",
                "description": "Documentação interativa da API RESTful de integração"
            },
            "authentication": {
                "preferredSecurityScheme": "bearerAuth"
            },
            "defaultOpenAllTags": true
        }'
    ></script>

    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>

</body>
</html>
