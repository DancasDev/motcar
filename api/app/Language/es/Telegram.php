<?php

/**
 * Traducciones en Español para el subsistema de Telegram Bot.
 * Organizado por áreas funcionales y flujos de usuario.
 */
return [
    // --------------------------------------------------------------------
    // 1. VINCULACIÓN Y AUTENTICACIÓN
    // --------------------------------------------------------------------
    'bind_success'               => "👋 <b>¡Hola!</b>\n\n✅ <b>Cuenta vinculada con éxito.</b>\nTu cuenta ha sido vinculada correctamente a este bot.\n\n📌 <b>Comandos disponibles:</b>\n• /estado — Consulta el estado de tu cuenta\n• /desvincular — Desvincula este chat de tu cuenta\n• /ayuda — Ver lista de opciones",
    'unbind_success'             => "👋 <b>Hasta pronto.</b>\n\n✅ <b>Cuenta desvinculada exitosamente.</b>\nEste chat ha sido desvinculado de tu cuenta y se han desactivado las notificaciones por este canal.\n\nSi deseas volver a vincularte en el futuro, genera un nuevo enlace desde la aplicación web.",
    'unbind_not_bound'           => "ℹ️ No tienes ninguna cuenta vinculada actualmente a este chat.",
    'already_bound'              => "Ya tienes un chat vinculado; primero desvincúlalo.",
    'no_pending'                 => "No hay un pendiente de vinculación vigente.",
    'vincular_prompt'            => "Para vincular tu cuenta, envía el token que se muestra en la app.",
    'ask_vincular_first'         => "Para vincular tu cuenta, genera un enlace desde la aplicación web o envía tu token de vinculación.",

    // --------------------------------------------------------------------
    // 2. COMANDOS BÁSICOS Y BIENVENIDA
    // --------------------------------------------------------------------
    'start_bound'                => "👋 <b>¡Hola de nuevo!</b>\n\nTu cuenta se encuentra vinculada a este bot.\n\n📌 <b>Opciones disponibles:</b>\n• /estado — Consulta el estado de tu cuenta\n• /desvincular — Desvincula este chat de tu cuenta\n• /ayuda — Ayuda y comandos",
    'start_unbound'              => "👋 <b>¡Bienvenido!</b>\n\nPara vincular tu cuenta a este bot:\n1️⃣ Inicia sesión en la plataforma.\n2️⃣ Ve a tu perfil y genera tu enlace de vinculación de Telegram.\n3️⃣ Abre el enlace generado desde este chat o envía el token directamente.\n\n💡 <i>Si tienes un token de vinculación, envíalo directamente en este chat.</i>",
    'help_header'                => "🤖 <b>Centro de Ayuda</b>\n\n📋 <b>Comandos disponibles:</b>\n• /start — Inicia el bot o vincula una cuenta con enlace directo\n• /estado — Consulta el estado de tu cuenta vinculada\n• /desvincular — Desvincula tu cuenta de Telegram del sistema\n• /ayuda — Muestra este mensaje de ayuda",
    'help_bound'                 => "\n\n✅ <i>Tu cuenta se encuentra vinculada.</i>",
    'help_unbound'               => "\n\n💡 <i>Para vincular tu cuenta, ingresa a tu perfil en la plataforma web.</i>",

    // --------------------------------------------------------------------
    // 3. ESTADO Y CANALES DE NOTIFICACIÓN
    // --------------------------------------------------------------------
    'status_bound'               => "📊 <b>Estado de la Cuenta:</b>\n\n✅ <b>Vinculación:</b> Activa\n🔔 <b>Notificaciones por Telegram:</b> {0}\n\n📌 <b>Opciones:</b>\n• /desvincular — Desvincular este chat de tu cuenta\n• /ayuda — Ver ayuda de comandos",
    'status_unbound'             => "ℹ️ <b>Estado: No vinculado</b>\n\nEste chat no está asociado a ninguna cuenta.\nPara vincularte, inicia sesión en la plataforma y genera tu enlace de vinculación.",
    'channel_telegram_enabled'   => "🔔 <b>Notificaciones Activadas</b>\n\nHas activado la recepción de notificaciones del sistema a través de Telegram.",
    'channel_telegram_disabled'  => "🔕 <b>Notificaciones Desactivadas</b>\n\nHas desactivado la recepción de notificaciones del sistema a través de Telegram. Seguirás teniendo acceso a las demás funciones de este bot.",
    'channel_active'             => "Habilitadas",
    'channel_inactive'           => "Deshabilitadas",

    // --------------------------------------------------------------------
    // 4. ERRORES Y VALIDACIONES
    // --------------------------------------------------------------------
    'token_invalid'              => "El token no es válido. Genera uno nuevo en la app.",
    'token_expired'              => "El token expiró. Genera uno nuevo en la app.",
    'token_used'                 => "El token ya fue utilizado. Genera uno nuevo en la app.",
    'webhook_unauthorized'       => "Solicitud no autorizada en el webhook de Telegram.",
    'channels_invalid'           => "El arreglo de canales no es válido (valores 0..4).",
    'error_unexpected'           => "⚠️ Ocurrió un error inesperado al procesar tu solicitud. Por favor intenta nuevamente más tarde.",
];
