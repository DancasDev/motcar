<?php

return [
    'user' => [
        'welcome'                   => '¡Hola, {name}! {sex, select, 0{Bienvenido} 1{Bienvenida} other{Bienvenid@}}, has iniciado sesión exitosamente.',
        'farewell'                  => '¡Hasta pronto, {name}! Has cerrado sesión de forma segura.',
        'faulted_attempts_exceeded' => 'Superaste los {0} intentos fallidos permitidos. Tu cuenta fue suspendida; restablece tu contraseña para desbloquearla.',
        'combination_not_found'     => 'El usuario o la contraseña proporcionados no son válidos. Por favor, verifica tus datos de acceso.',
    ],
    'client' => [
        'welcome'                   => 'Bienvenido {name}',
        'farewell'                  => 'Hasta luego, {name}',
        'faulted_attempts_exceeded' => 'Cliente inhabilitado por exceder el límite de {0} intentos fallidos de sesión.',
        'combination_not_found'     => 'Combinación de client_id y client_secret no encontrada.',
    ],
    'env_variables_not_defined'     => 'Error de configuración del servidor.',
    'token_update_invalid'          => 'Deben pasar {0} minutos antes de renovar el token.',
];
