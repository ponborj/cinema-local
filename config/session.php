<?php

use Illuminate\Support\Str;

return [

    /* Local usado para guardar os dados da sessão. */

    'driver' => env('SESSION_DRIVER', 'database'),

    /* Tempo de inatividade em minutos e opção de encerrar ao fechar o navegador. */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /* Ativa a criptografia dos dados da sessão antes de salvar. */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /* Pasta usada quando a sessão é salva em arquivos. */

    'files' => storage_path('framework/sessions'),

    /* Conexão usada para sessões no banco ou no Redis. */

    'connection' => env('SESSION_CONNECTION'),

    /* Tabela que guarda as sessões no banco. */

    'table' => env('SESSION_TABLE', 'sessions'),

    /* Armazenamento de cache usado pelas sessões. */

    'store' => env('SESSION_STORE'),

    /* Chance de limpar sessões antigas em cada requisição. */

    'lottery' => [2, 100],

    /* Nome do cookie que identifica a sessão. */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /* Caminho do site em que o cookie da sessão é válido. */

    'path' => env('SESSION_PATH', '/'),

    /* Domínio em que o cookie da sessão é válido. */

    'domain' => env('SESSION_DOMAIN'),

    /* Limita o envio do cookie a conexões HTTPS. */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /* Impede que o JavaScript leia o cookie da sessão. */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /* Controla o envio do cookie em acessos vindos de outros sites. */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /* Separa o cookie por site; exige HTTPS e SameSite definido como none. */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    /* Formato usado para salvar os dados da sessão. O padrão é JSON. */

    'serialization' => 'json',

];
