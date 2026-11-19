<?php

$core = [
    // ---------------------------------------------------------------
    //  Plantillas de Contenido de Notificaciones (Eventos del Sistema)
    // ---------------------------------------------------------------
    'user_welcome' => [
        'title' => '¡Bienvenido a {app_name}!',
        'inapp' => 'Hola {user_name}, te damos la bienvenida a la plataforma. Tu cuenta está lista para ser utilizada.',
        'email' => 'Hola {user_name}, te damos la bienvenida a {app_name}. Tu cuenta está activa y lista para usar.',
        'push'  => '¡Bienvenido a {app_name}! Tu cuenta está lista para ser utilizada.',
    ],
    'user_locked' => [
        'title' => 'Cuenta bloqueada por seguridad',
        'inapp' => 'Tu cuenta ha sido bloqueada temporalmente tras superar el límite de intentos fallidos de inicio de sesión.',
        'email' => 'Tu cuenta ha sido bloqueada temporalmente tras superar el número máximo de intentos fallidos de acceso desde la IP {ip}.',
        'push'  => 'Tu cuenta ha sido bloqueada temporalmente por seguridad.',
    ],
    'password_updated' => [
        'title' => 'Contraseña actualizada',
        'inapp' => 'Se ha actualizado la contraseña de tu cuenta desde la IP {ip}.',
        'email' => 'Se ha actualizado la contraseña de tu cuenta desde la IP {ip}. Si no realizaste esta acción, por favor revisa el registro de actividad.',
        'push'  => 'Se ha modificado la contraseña de tu cuenta.',
    ],
    'test_notification' => [
        'title' => '🔔 Notificación de Prueba',
        'push'  => 'Tus notificaciones Push están configuradas y funcionando correctamente.',
    ],
    'cta_default' => 'Ver en la plataforma',
];

$custom = [
    // 'types' => [
    //     'order_paid' => ['name' => 'Orden pagada', 'description' => 'Notificación enviada al completarse el pago de una orden.'],
    // ],
    // 'order_paid' => [
    //     'title' => 'Orden #{order_id} pagada',
    //     'inapp' => 'Tu orden ha sido pagada con éxito.',
    //     'email' => 'Tu orden #{order_id} por {amount} ha sido confirmada.',
    //     'push'  => 'Orden #{order_id} pagada con éxito.',
    // ],
];

return empty($custom) ? $core : array_replace_recursive($core, $custom);