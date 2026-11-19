<?php

return [
    'password_recovery' => [
        'subject'              => 'Recuperación de contraseña',
        'sent'                 => 'Si el correo ingresado coincide con una cuenta activa, recibirás instrucciones de recuperación en breve.',
        'success'              => 'La contraseña ha sido restablecida exitosamente.',
        'error_user_not_found' => 'La cuenta de usuario no está disponible.',
        'error_send_email'     => 'Ocurrió un error al enviar el correo de recuperación. Intenta de nuevo más tarde.',
    ],
    'email_verification' => [
        'subject'              => 'Verificación de correo electrónico',
        'sent'                 => 'Se ha enviado un código de verificación a tu correo electrónico. Expirará en 10 minutos.',
        'success'              => 'El correo electrónico ha sido verificado exitosamente.',
        'already_verified'     => 'El correo electrónico ya ha sido verificado previamente.',
        'email_not_found'      => 'El usuario no tiene una dirección de correo electrónico registrada.',
        'error_user_not_found' => 'La cuenta de usuario no está disponible.',
        'error_send_email'     => 'Ocurrió un error al enviar el correo de verificación. Intenta de nuevo más tarde.',
    ],
    'password_setup' => [
        'success'           => 'La contraseña ha sido establecida exitosamente.',
        'username_mismatch' => 'El nombre de usuario no coincide con la invitación.',
    ],
    'user_invitation' => [
        'subject'          => 'Invitación al sistema',
        'sent'             => 'Invitación generada exitosamente.',
        'greeting'         => 'Hola {0},',
        'body'             => 'Has sido invitado a formar parte del sistema. Para configurar tu contraseña y activar tu cuenta, haz clic en el siguiente enlace:',
        'username_info'    => 'Tu nombre de usuario asignado es: <strong>{0}</strong>',
        'button'           => 'Configurar Contraseña',
        'expires'          => 'Este enlace de invitación expirará el {0}.',
        'error_send_email' => 'Ocurrió un error al enviar el correo de invitación. Intenta de nuevo más tarde.',
    ],
    'otp_email' => [
        'greeting'   => 'Tu código de verificación es:',
        'expires'    => 'Este código expira el {0}.',
        'disclaimer' => 'Si no solicitaste este código, ignora este mensaje.',
    ],
    'email_footer' => [
        'auto_generated' => 'Este mensaje fue generado automáticamente por {0}. Por favor no respondas a este correo.',
        'copyright'      => 'MOTCAR © {0} • Todos los derechos reservados',
    ],
];
