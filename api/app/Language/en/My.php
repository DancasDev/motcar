<?php

return [
    // ---------------------------------------------------------------
    //  Profile and Security
    // ---------------------------------------------------------------
    'profile' => [
        'not_found'            => 'User profile not found.',
        'updated_successfully' => 'Profile updated successfully.',
    ],
    'password' => [
        'current_invalid'      => 'The current password is incorrect.',
        'same_as_current'      => 'The new password must be different from the current password.',
        'updated_successfully' => 'Password updated successfully. Please log in with your new password.',
    ],

    // ---------------------------------------------------------------
    //  Activity Binnacles
    // ---------------------------------------------------------------
    'binnacles' => [
        'not_found' => 'Binnacle record not found.',
    ],

    // ---------------------------------------------------------------
    //  User Notifications Controllers (API Responses)
    // ---------------------------------------------------------------
    'notifications' => [
        'not_found'      => 'Notification not found.',
        'marked_as_read' => 'Notification marked as read successfully.',
    ],
    'channels' => [
        'invalid_format'       => 'The "notification_channels" field is required and must have a valid format.',
        'channel_unavailable'  => 'One or more selected channels are not available to be enabled.',
        'updated_successfully' => 'Notification channels updated successfully.',
    ],
    'push' => [
        'channel_disabled'  => 'The Push notification channel is disabled in the system.',
        'register_failed'   => 'Could not register push device.',
        'device_registered' => 'Push device registered successfully.',
        'device_not_found'  => 'Push device not found.',
        'device_updated'    => 'Push device updated successfully.',
        'device_deleted'    => 'Push device deleted successfully.',
        'no_active_devices' => 'You have no active push devices registered for testing.',
        'send_failed'       => 'Failed to send test push notification.',
        'test_sent'         => 'Test push notification sent successfully.',
    ],
    'telegram' => [
        'channel_disabled'     => 'The Telegram channel is disabled in the system.',
        'already_bound'        => 'You already have a linked Telegram account.',
        'not_bound'            => 'You do not have a linked Telegram account.',
        'link_generated'       => 'Binding link generated successfully.',
        'unbound_successfully' => 'Telegram account unlinked successfully.',
        'send_failed'          => 'Failed to send message via Telegram.',
        'test_sent'            => 'Test message sent successfully via Telegram.',
    ],
];