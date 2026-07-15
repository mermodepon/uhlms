<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'views' => false,
    'home' => '/admin',
    'prefix' => 'admin/security',
    'domain' => null,
    'lowercase_usernames' => true,
    'limiters' => ['login' => null, 'passkeys' => null],
    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
