<?php

/**
 * English translations for the Telegram Bot subsystem.
 * Organized by functional areas and user flows.
 */
return [
    // --------------------------------------------------------------------
    // 1. BINDING & AUTHENTICATION
    // --------------------------------------------------------------------
    'bind_success'               => "👋 <b>Hello!</b>\n\n✅ <b>Account linked successfully.</b>\nYour account has been linked to this bot.\n\n📌 <b>Available commands:</b>\n• /estado — Check your account status\n• /desvincular — Unlink this chat from your account\n• /ayuda — View available options",
    'unbind_success'             => "👋 <b>See you soon.</b>\n\n✅ <b>Account unlinked successfully.</b>\nThis chat has been unlinked from your account and notifications via this channel have been disabled.\n\nIf you wish to link again in the future, generate a new link in the web app.",
    'unbind_not_bound'           => "ℹ️ You do not have any account currently linked to this chat.",
    'already_bound'              => "You already have a linked chat; unlink it first.",
    'no_pending'                 => "There is no active pending binding.",
    'vincular_prompt'            => "To link your account, send the token shown in the app.",
    'ask_vincular_first'         => "To link your account, generate a link in the web app or send your binding token.",

    // --------------------------------------------------------------------
    // 2. BASIC COMMANDS & WELCOME
    // --------------------------------------------------------------------
    'start_bound'                => "👋 <b>Hello again!</b>\n\nYour account is linked to this bot.\n\n📌 <b>Available options:</b>\n• /estado — Check your account status\n• /desvincular — Unlink this chat from your account\n• /ayuda — Help and commands",
    'start_unbound'              => "👋 <b>Welcome!</b>\n\nTo link your account to this bot:\n1️⃣ Log in to the platform.\n2️⃣ Go to your profile and generate your Telegram binding link.\n3️⃣ Open the generated link from this chat or send the token directly.\n\n💡 <i>If you have a binding token, send it directly in this chat.</i>",
    'help_header'                => "🤖 <b>Help Center</b>\n\n📋 <b>Available commands:</b>\n• /start — Start the bot or link an account with a direct link\n• /estado — Check the status of your linked account\n• /desvincular — Unlink your Telegram account from the system\n• /ayuda — Show this help message",
    'help_bound'                 => "\n\n✅ <i>Your account is currently linked.</i>",
    'help_unbound'               => "\n\n💡 <i>To link your account, go to your profile on the web platform.</i>",

    // --------------------------------------------------------------------
    // 3. STATUS & NOTIFICATION CHANNELS
    // --------------------------------------------------------------------
    'status_bound'               => "📊 <b>Account Status:</b>\n\n✅ <b>Binding:</b> Active\n🔔 <b>Telegram Notifications:</b> {0}\n\n📌 <b>Options:</b>\n• /desvincular — Unlink this chat from your account\n• /ayuda — View help commands",
    'status_unbound'             => "ℹ️ <b>Status: Not linked</b>\n\nThis chat is not associated with any account.\nTo link, log in to the platform and generate your binding link.",
    'channel_telegram_enabled'   => "🔔 <b>Notifications Enabled</b>\n\nYou have enabled system notifications via Telegram.",
    'channel_telegram_disabled'  => "🔕 <b>Notifications Disabled</b>\n\nYou have disabled system notifications via Telegram. You will still have access to other bot features.",
    'channel_active'             => "Enabled",
    'channel_inactive'           => "Disabled",

    // --------------------------------------------------------------------
    // 4. ERRORS & VALIDATIONS
    // --------------------------------------------------------------------
    'token_invalid'              => "The token is not valid. Generate a new one in the app.",
    'token_expired'              => "The token expired. Generate a new one in the app.",
    'token_used'                 => "The token was already used. Generate a new one in the app.",
    'webhook_unauthorized'       => "Unauthorized request to the Telegram webhook.",
    'channels_invalid'           => "The channels array is not valid (values 0..4).",
    'error_unexpected'           => "⚠️ An unexpected error occurred while processing your request. Please try again later.",
];
