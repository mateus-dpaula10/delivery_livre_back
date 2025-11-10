<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // Quais caminhos da API aceitarão CORS
    'paths' => ['api/*'],

    // Quais métodos HTTP são permitidos
    'allowed_methods' => ['*'],

    // Quais origens são permitidas
    'allowed_origins' => [
        '*', // Para desenvolvimento. Em produção, substitua pelo domínio do seu app
        'https://bgqdxxc-mateusdpaula10-8081.exp.direct', // Expo Tunnel
        'https://*.ngrok-free.dev',                        // ngrok
    ],

    // Expressões regulares para origens
    'allowed_origins_patterns' => [],

    // Cabeçalhos permitidos
    'allowed_headers' => ['*'],

    // Cabeçalhos expostos
    'exposed_headers' => [],

    // Tempo máximo em segundos que o navegador pode armazenar a política
    'max_age' => 0,

    // Permitir credenciais (cookies, auth headers)
    'supports_credentials' => true,

];