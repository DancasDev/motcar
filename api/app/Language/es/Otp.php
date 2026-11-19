<?php

$core = [
    'actions' => [
        'password_recovery'  => 'Recuperación de contraseña',
        'email_verification' => 'Verificación de correo electrónico',
    ],
    'action_not_found'  => 'La acción solicitada no está disponible.',
    'record_not_found'  => 'El código de verificación no existe.',
    'input_required'    => 'El verification_id y otp_code son requeridos.',
    'user_mismatch'     => 'El código de verificación no corresponde al usuario.',
    'action_mismatch'   => 'El código de verificación no corresponde a la acción solicitada.',
    'already_used'      => 'El código de verificación ya fue utilizado.',
    'expired'           => 'El código de verificación ha expirado.',
    'attempts_exceeded' => 'Has agotado los intentos permitidos.',
    'invalid_code'      => 'El código de verificación es incorrecto.',
    'pending_active'    => 'Ya tienes un código activo. Espera {0} segundos antes de solicitar uno nuevo.',
    'email_greeting'    => 'Tu código de verificación es:',
    'email_expires'     => 'Este código expira el {0}.',
    'email_disclaimer'  => 'Si no solicitaste este código, ignora este mensaje.',
];

$custom = [
    'actions' => [
        // 'phone_verification' => 'Verificación de número telefónico',
    ],
];

return empty($custom['actions']) ? $core : array_replace_recursive($core, $custom);
