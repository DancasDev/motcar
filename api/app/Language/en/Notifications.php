<?php

$core = [
    // ---------------------------------------------------------------
    //  Notification Content Templates (System Events)
    // ---------------------------------------------------------------
    'user_welcome' => [
        'title' => 'Welcome to {app_name}!',
        'inapp' => 'Hello {user_name}, welcome to the platform. Your account is ready to use.',
        'email' => 'Hello {user_name}, welcome to {app_name}. Your account is active and ready to use.',
        'push'  => 'Welcome to {app_name}! Your account is ready to use.',
    ],
    'user_locked' => [
        'title' => 'Account Locked for Security',
        'inapp' => 'Your account has been temporarily locked after exceeding the maximum failed login attempts.',
        'email' => 'Your account has been temporarily locked after exceeding the maximum failed login attempts from IP {ip}.',
        'push'  => 'Your account has been temporarily locked for security.',
    ],
    'password_updated' => [
        'title' => 'Password Updated',
        'inapp' => 'Your account password was updated from IP {ip}.',
        'email' => 'Your account password was updated from IP {ip}. If you did not perform this action, please check your activity log.',
        'push'  => 'Your account password was modified.',
    ],
    'test_notification' => [
        'title' => '🔔 Test Notification',
        'push'  => 'Your Push notifications are configured and working properly.',
    ],
    'cta_default' => 'View in Platform',
];

$custom = [
    // 'types' => [
    //     'order_paid' => ['name' => 'Order Paid', 'description' => 'Notification sent when order payment is completed.'],
    // ],
    // 'order_paid' => [
    //     'title' => 'Order #{order_id} Paid',
    //     'inapp' => 'Your order has been paid successfully.',
    //     'email' => 'Your order #{order_id} for {amount} has been confirmed.',
    //     'push'  => 'Order #{order_id} paid successfully.',
    // ],
];

return empty($custom) ? $core : array_replace_recursive($core, $custom);