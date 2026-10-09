<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Permite que o frontend JavaScript (rodando em outra origem) consuma a
    | API. As origens autorizadas sao definidas em CORS_ALLOWED_ORIGINS,
    | separadas por virgula (ex.: http://localhost:5500,http://127.0.0.1:5500).
    | O valor "*" libera todas as origens (util apenas em desenvolvimento).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
