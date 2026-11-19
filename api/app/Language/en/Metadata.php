<?php

return [
    'attributes' => [
        'invalid_type'            => 'The field type is invalid.',
        'invalid_attribute_values' => 'The field attributes are invalid.',
    ],
    'rules' => [
        'invalid_format' => 'The field must be an array of rules.',
        'invalid_rule'   => 'The validation rule has an invalid format.',
        'unknown_rule'   => 'The validation rule "{0}" does not exist.',
        'invalid_params' => 'The parameters of rule "{0}" are invalid.',
    ],
    'unique'                          => 'The value is already registered.',
    'unique_composite'                => 'This combination of fields is already registered.',
    'cannot_change_code_with_records'   => 'Cannot modify field code because records already exist.',
    'group_schema_mismatch'      => 'The selected group does not belong to the field schema.',
    'unique_keys_invalid_format' => 'The unique keys format is invalid. It must be a list of field groups, e.g.: [["field_a", "field_b"]].',
];
