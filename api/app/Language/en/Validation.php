<?php

// override core en language system validation or define your own en language validation message
return [
    // Core Messages
    'noRuleSets'      => 'No rule sets specified in Validation configuration.',
    'ruleNotFound'    => '"{0}" is not a valid rule.',
    'groupNotFound'   => '"{0}" is not a validation rules group.',
    'groupNotArray'   => '"{0}" rule group must be an array.',
    'invalidTemplate' => '"{0}" is not a valid Validation template.',

    // Rule Messages
    'alpha'                      => 'The field may only contain alphabetical characters.',
    'alpha_dash'                 => 'The field may only contain alphanumeric, underscore, and dash characters.',
    'alpha_numeric'              => 'The field may only contain alphanumeric characters.',
    'alpha_numeric_punct'        => 'The field may contain only alphanumeric characters, spaces, and  ~ ! # $ % & * - _ + = | : . characters.',
    'alpha_numeric_space'        => 'The field may only contain alphanumeric and space characters.',
    'alpha_space'                => 'The field may only contain alphabetical characters and spaces.',
    'decimal'                    => 'The field must contain a decimal number.',
    'differs'                    => 'The field must differ from the {param} field.',
    'date_greater_than'          => 'The field must contain a date after {param}.',
    'date_greater_than_equal_to' => 'The field must contain a date after or equal to {param}.',
    'date_less_than'             => 'The field must contain a date before {param}.',
    'date_less_than_equal_to'    => 'The field must contain a date before or equal to {param}.',
    'equals'                     => 'The field must be exactly: {param}.',
    'exact_length'               => 'The field must be exactly {param} characters in length.',
    'field_exists'               => 'The field must exist.',
    'greater_than'               => 'The field must contain a number greater than {param}.',
    'greater_than_equal_to'      => 'The field must contain a number greater than or equal to {param}.',
    'hex'                        => 'The field may only contain hexadecimal characters.',
    'in_list'                    => 'The field must be one of: {param}.',
    'integer'                    => 'The field must contain an integer.',
    'is_array'                   => 'The field must be an array.',
    'is_natural'                 => 'The field must only contain digits.',
    'is_natural_no_zero'         => 'The field must only contain digits and must be greater than zero.',
    'is_not_unique'              => 'The field must contain a previously existing value in the database.',
    'is_unique'                  => 'The field must contain a unique value.',
    'less_than'                  => 'The field must contain a number less than {param}.',
    'less_than_equal_to'         => 'The field must contain a number less than or equal to {param}.',
    'matches'                    => 'The field does not match the {param} field.',
    'max_length'                 => 'The field cannot exceed {param} characters in length.',
    'min_length'                 => 'The field must be at least {param} characters in length.',
    'not_equals'                 => 'The field cannot be: {param}.',
    'not_in_list'                => 'The field must not be one of: {param}.',
    'numeric'                    => 'The field must contain only numbers.',
    'regex_match'                => 'The field is not in the correct format.',
    'required'                   => 'The field is required.',
    'required_with'              => 'The field is required when {param} is present.',
    'required_without'           => 'The field is required when {param} is not present.',
    'string'                     => 'The field must be a valid string.',
    'timezone'                   => 'The field must be a valid timezone.',
    'valid_base64'               => 'The field must be a valid base64 string.',
    'valid_email'                => 'The field must contain a valid email address.',
    'valid_emails'               => 'The field must contain all valid email addresses.',
    'valid_ip'                   => 'The field must contain a valid IP.',
    'valid_url'                  => 'The field must contain a valid URL.',
    'valid_url_strict'           => 'The field must contain a valid URL.',
    'valid_date'                 => 'The field must contain a valid date.',
    'valid_json'                 => 'The field must contain a valid json.',

    // Credit Cards
    'valid_cc_number' => 'Does not appear to be a valid credit card number.',

    // Files
    'uploaded' => 'Is not a valid uploaded file.',
    'max_size' => 'Is too large of a file.',
    'is_image' => 'Is not a valid, uploaded image file.',
    'mime_in'  => 'Does not have a valid mime type.',
    'ext_in'   => 'Does not have a valid file extension.',
    'max_dims' => 'Is either not an image, or it is too wide or tall.',
    'min_dims' => 'Is either not an image, or it is not wide or tall enough.',

    // GAC Scope Validation
    'scopeInvalid'   => 'The scope_path has an invalid format. Use paths like "1", "1/2", "1/*", or comma-separated combinations: "1/2,1/3".',
    'scopeCollision' => 'The scope_path contains a route that already exists for this entity and module.',

    // i18n Validation
    'valid_i18n'            => 'The field must be an object with translations per language.',
    'valid_i18n_lang'       => 'The language "{0}" is not supported.',
    'valid_i18n_string'     => 'The translation for "{0}" must be a non-empty string.',
    'valid_i18n_max_length' => 'The translation for "{0}" cannot exceed {1} characters.',
    'valid_i18n_min_length' => 'The translation for "{0}" must be at least {1} characters.',
];
