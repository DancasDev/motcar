<?php

return [
    'auth' => [
        'token_is_required'           => 'Debes iniciar sesión para acceder a este recurso.',
        'token_is_invalid'            => 'El token de autorización no es válido.',
        'token_is_expired'            => 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.',
        'token_is_disabled'           => 'Tu sesión ha finalizado. Por favor, inicia sesión nuevamente.',
        'token_is_refused'            => 'Tu sesión fue cerrada por seguridad debido a un cambio de red. Inicia sesión nuevamente.',
        'only_client'                 => 'Este recurso es exclusivo para integraciones de API.',
        'only_user'                   => 'Este recurso solo está disponible para usuarios con sesión activa.',
        'email_verification_required' => 'Debes verificar tu correo electrónico para poder continuar.',
        'password_change_required'    => 'Debes cambiar tu contraseña para poder continuar.',
        'branch_not_available'        => 'La sucursal (ID: {0}) no está disponible.',
    ],
    'restriction' => [
        'load_exception' => 'Error al cargar las restricciones.',
        'by_date_fail' => [
            'before'   => 'La acción solo se podia realizar antes del "{d}".',
            'after'    => 'La acción solo se puede realizar después del "{d}".',
            'in_range' => 'La acción solo se puede realizar entre "{sd}" y "{ed}".',
            'out_range' => 'La acción no se puede realizar entre "{sd}" y "{ed}".',
        ],
        'by_ip_fail' => [
            'allow' => 'La acción no se puede realizar porque la IP actual no está en la lista de IPs permitidas.',
            'deny'  => 'La acción no se puede realizar porque la IP actual está en la lista de IPs denegadas.',
        ],
        'by_domain_fail' => [
            'allow' => 'La acción no se puede realizar porque el dominio actual no está en la lista de dominios permitidos.',
            'deny'  => 'La acción no se puede realizar porque el dominio actual está en la lista de dominios denegados.',
        ],
    ],
    'permision' => [
        'load_exception'       => 'Error al cargar los permisos.',
        'refused'              => 'No tienes permiso para acceder al módulo "{0}".',
        'module_is_developing' => 'El módulo "{0}" se encuentra en mantenimiento y no está disponible.',
        'refused_action'       => 'No tienes permiso para realizar esta acción.',
    ],
];
