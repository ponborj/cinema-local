<?php

use App\Models\User;

return [

    /* Configuração padrão de login e recuperação de senha. */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /* Formas de identificar o usuário durante uma requisição. */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /* Origem dos dados dos usuários. */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /* Validade do link de recuperação em minutos e intervalo entre pedidos em segundos. */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /* Tempo, em segundos, até pedir a confirmação da senha novamente. */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
