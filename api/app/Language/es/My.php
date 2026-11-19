<?php

return [
    // ---------------------------------------------------------------
    //  Perfil y Seguridad
    // ---------------------------------------------------------------
    'profile' => [
        'not_found'            => 'Perfil de usuario no encontrado.',
        'updated_successfully' => 'Perfil actualizado exitosamente.',
    ],
    'password' => [
        'current_invalid'      => 'La contraseña actual es incorrecta.',
        'same_as_current'      => 'La nueva contraseña debe ser diferente a la contraseña actual.',
        'updated_successfully' => 'Contraseña actualizada exitosamente. Por favor, inicia sesión con tu nueva contraseña.',
    ],

    // ---------------------------------------------------------------
    //  Bitácoras de Actividad
    // ---------------------------------------------------------------
    'binnacles' => [
        'not_found' => 'Registro de bitácora no encontrado.',
    ],

    // ---------------------------------------------------------------
    //  Controladores de Notificaciones del Usuario (API Responses)
    // ---------------------------------------------------------------
    'notifications' => [
        'not_found'      => 'Notificación no encontrada.',
        'marked_as_read' => 'Notificación marcada como leída exitosamente.',
    ],
    'channels' => [
        'invalid_format'       => 'El campo "notification_channels" es obligatorio y debe tener un formato válido.',
        'channel_unavailable'  => 'Uno o más canales seleccionados no están disponibles para ser habilitados.',
        'updated_successfully' => 'Canales de notificación actualizados exitosamente.',
    ],
    'push' => [
        'channel_disabled'  => 'El canal de notificaciones Push está deshabilitado en el sistema.',
        'register_failed'   => 'No se pudo registrar el dispositivo push.',
        'device_registered' => 'Dispositivo push registrado exitosamente.',
        'device_not_found'  => 'Dispositivo push no encontrado.',
        'device_updated'    => 'Dispositivo push actualizado exitosamente.',
        'device_deleted'    => 'Dispositivo push eliminado exitosamente.',
        'no_active_devices' => 'No tienes dispositivos push activos registrados para realizar la prueba.',
        'send_failed'       => 'Fallo al enviar la notificación push de prueba.',
        'test_sent'         => 'Notificación push de prueba enviada exitosamente.',
    ],
    'telegram' => [
        'channel_disabled'     => 'El canal Telegram está deshabilitado en el sistema.',
        'already_bound'        => 'Ya tienes una cuenta de Telegram vinculada.',
        'not_bound'            => 'No tienes una cuenta de Telegram vinculada.',
        'link_generated'       => 'Enlace de vinculación generado exitosamente.',
        'unbound_successfully' => 'Cuenta de Telegram desvinculada exitosamente.',
        'send_failed'          => 'Fallo al enviar mensaje por Telegram.',
        'test_sent'            => 'Mensaje de prueba enviado exitosamente por Telegram.',
    ],
];