<?php

return [
    'auth' => [
        'token_is_required'           => 'You must log in to access this resource.',
        'token_is_invalid'            => 'Authorization token is invalid.',
        'token_is_expired'            => 'Your session has expired. Please log in again.',
        'token_is_disabled'           => 'Your session has ended. Please log in again.',
        'token_is_refused'            => 'Your session was terminated for security due to a network change. Please log in again.',
        'only_client'                 => 'This resource is reserved exclusively for API integrations.',
        'only_user'                   => 'This resource is only available for users with an active session.',
        'email_verification_required' => 'You must verify your email address to continue.',
        'password_change_required'    => 'You must change your password to continue.',
        'branch_not_available'        => 'Branch (ID: {0}) is not available.',
    ],
    'restriction' => [
        'load_exception' => 'Error loading restrictions.',
        'by_date_fail' => [
            'before'   => 'The action could only be performed before "{d}".',
            'after'    => 'The action can only be performed after "{d}".',
            'in_range' => 'The action can only be performed between "{sd}" and "{ed}".',
            'out_range' => 'The action cannot be performed between "{sd}" and "{ed}".',
        ],
        'by_ip_fail' => [
            'allow' => 'The action cannot be performed because the current IP is not in the allowed list.',
            'deny'  => 'The action cannot be performed because the current IP is in the denied list.',
        ],
        'by_domain_fail' => [
            'allow' => 'The action cannot be performed because the current domain is not in the allowed list.',
            'deny'  => 'The action cannot be performed because the current domain is in the denied list.',
        ],
    ],
    'permision' => [
        'load_exception'       => 'Error loading permissions.',
        'refused'              => 'You do not have permission to access the "{0}" module.',
        'module_is_developing' => 'The module "{0}" is under maintenance and currently unavailable.',
        'refused_action'       => 'You do not have permission to perform this action.',
    ],
];
