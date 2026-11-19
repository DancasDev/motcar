<?php

return [
    'attributes' => [
        'invalid_type'            => 'El tipo de campo es inválido.',
        'invalid_attribute_values' => 'Los atributos del campo son inválidos.',
    ],
    'rules' => [
        'invalid_format' => 'El campo debe ser un arreglo de reglas.',
        'invalid_rule'   => 'La regla de validación tiene un formato inválido.',
        'unknown_rule'   => 'La regla de validación "{0}" no existe.',
        'invalid_params' => 'Los parámetros de la regla "{0}" son inválidos.',
    ],
    'unique'                          => 'El valor ya se encuentra registrado.',
    'unique_composite'                => 'Esta combinación de campos ya se encuentra registrada.',
    'cannot_change_code_with_records'   => 'No se puede modificar el código del campo porque ya existen registros asociados.',
    'group_schema_mismatch'       => 'El grupo seleccionado no pertenece al esquema del campo.',
    'unique_keys_invalid_format'  => 'El formato de las claves únicas es inválido. Debe ser una lista de grupos de campos, ej: [["campo_a", "campo_b"]].',
];
