<?php

return [

    /* Nome exibido nas mensagens da aplicação. */

    'name' => env('APP_NAME', 'Laravel'),

    /* Ambiente definido no arquivo .env. */

    'env' => env('APP_ENV', 'production'),

    /* Mostra detalhes dos erros quando o modo de depuração está ativo. */

    'debug' => (bool) env('APP_DEBUG', false),

    /* Endereço usado para gerar links nos comandos Artisan. */

    'url' => env('APP_URL', 'http://localhost'),

    /* Fuso horário usado pelas funções de data e hora. */

    'timezone' => 'UTC',

    /* Idioma principal, idioma alternativo e idioma dos dados de teste. */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /* Chave usada para criptografar os dados da aplicação. */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /* Forma de guardar e consultar o estado de manutenção. */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
