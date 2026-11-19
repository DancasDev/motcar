<?php

$core = [
    'actions' => [
        'password_recovery'  => 'Password recovery',
        'email_verification' => 'Email verification',
    ],
    'action_not_found'  => 'The requested action is not available.',
    'record_not_found'  => 'The verification record was not found.',
    'input_required'    => 'Verification_id and otp_code are required.',
    'user_mismatch'     => 'The verification code does not match the user.',
    'action_mismatch'   => 'The verification code does not match the requested action.',
    'already_used'      => 'The verification code has already been used.',
    'expired'           => 'The verification code has expired.',
    'attempts_exceeded' => 'You have exceeded the maximum number of attempts.',
    'invalid_code'      => 'The verification code is incorrect.',
    'pending_active'    => 'You already have an active code. Wait {0} seconds before requesting a new one.',
    'email_greeting'    => 'Your verification code is:',
    'email_expires'     => 'This code expires on {0}.',
    'email_disclaimer'  => 'If you did not request this code, please ignore this message.',
];

$custom = [
    'actions' => [
        // 'phone_verification' => 'Phone verification',
    ],
];

return empty($custom['actions']) ? $core : array_replace_recursive($core, $custom);
