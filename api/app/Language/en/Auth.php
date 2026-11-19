<?php

return [
    'user' => [
        'welcome'                   => 'Hello, {name}! {sex, select, 0{Welcome} 1{Welcome} other{Welcome}}, you have logged in successfully.',
        'farewell'                  => 'Goodbye, {name}! You have logged out securely.',
        'faulted_attempts_exceeded' => 'You exceeded the limit of {0} failed attempts. Your account has been suspended; reset your password to regain access.',
        'combination_not_found'     => 'The username or password provided is invalid. Please check your credentials.',
    ],
    'client' => [
        'welcome'                   => 'Welcome {name}',
        'farewell'                  => 'Goodbye, {name}',
        'faulted_attempts_exceeded' => 'Client disabled due to exceeding the limit of {0} failed login attempts.',
        'combination_not_found'     => 'Combination of client_id and client_secret not found.',
    ],
    'env_variables_not_defined'     => 'Server configuration error.',
    'token_update_invalid'          => '{0} minutes must pass before refreshing the token.',
];
