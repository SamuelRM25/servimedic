<?php
/**
 * Configuración de transportistas para el generador de guías.
 * Copia este archivo como shipping.php y llena las credenciales reales.
 * NOTA: shipping.php está en .gitignore y NO debe subirse al repositorio.
 *
 * Credenciales usadas SOLO por el servidor (portal_proxy.php) para
 * precargar el login dentro del iframe embebido. Nunca se guardan en BD.
 */
return [
    'forza' => [
        'label'  => 'Forza Delivery',
        'base'   => 'https://portal.forzadelivery.com',
        'entry'  => '/login-corporate',
        'path_prefixes' => [],
        'autologin' => [
            'file'  => __DIR__ . '/../php/shipping/autologin_forza.js',
            'vars'  => [
                'service' => 'forza',
                'code'    => 'CODIGO_CLIENTE_FORZA',
                'user'    => 'USUARIO_FORZA',
                'pass'    => 'CONTRASENA_FORZA',
            ],
        ],
    ],
    'cargo' => [
        'label'  => 'Cargo Expreso',
        'base'   => 'https://portal.cargo-expreso.com',
        'entry'  => '/',
        'path_prefixes' => ['/ic/', '/cloudgate/', '/ords/'],
        // El login de Cargo usa OIDC contra el IDCS del tenant: el host de
        // IDCS se sirve también por el proxy (servicio 'idcs') para que el
        // authorize/token quede same-origin y las cookies vivan en el jar.
        'extra_rewrites' => [
            'https://idcs-d305c6908a9c416180f48a476e37f5ce.identity.oraclecloud.com' => 'idcs',
        ],
        'autologin' => [
            'file'  => __DIR__ . '/../php/shipping/autologin_cargo.js',
            'vars'  => [
                'service' => 'cargo',
                'email'   => 'CORREO_CARGO',
                'pass'    => 'CONTRASENA_CARGO',
            ],
        ],
    ],
    'idcs' => [
        'label'  => 'IDCS Oracle (Cargo Expreso)',
        'base'   => 'https://idcs-d305c6908a9c416180f48a476e37f5ce.identity.oraclecloud.com',
        'entry'  => '/',
        'path_prefixes' => ['/oauth2/', '/sso/', '/admin/', '/entitlements/'],
        // IDCS valida el Origin de navegador: simular el del portal real.
        'origin' => 'https://portal.cargo-expreso.com',
        'referer' => 'https://portal.cargo-expreso.com/',
        // El authorize redirige de vuelta al portal con ?code=... : reescribir
        // ese Location al proxy de cargo para que el iframe no salga a internet.
        'extra_rewrites' => [
            'https://portal.cargo-expreso.com' => 'cargo',
        ],
        'autologin' => null,
    ],
];
