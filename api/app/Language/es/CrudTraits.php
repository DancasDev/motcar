<?php

return [
    'input' => [
        'limit_exceeded' => 'El número de elementos a procesar excede el límite de {0} registros por solicitud.',
        'duplicate_pk' => 'El lote contiene identificadores duplicados.'
    ],
    'model' => [
        'is_undefined' => 'La acción no se puede realizar porque el modelo no está definido correctamente en el controlador.',
        'delete_field_is_required' => 'La acción no se puede realizar porque el campo que marca el borrado lógico de los registros en la tabla no está definido en el modelo.',
    ],
    'schema' => [
        'is_undefined' => 'La acción no se puede realizar porque el esquema no está definido correctamente en el controlador.',
    ],
    'binnacle' => [
        'error' => 'Error al intentar registrar la acción en la bitácora.'
    ],
    'create' => [
        'error' => 'Error al intentar crear un registro en la base de datos.',
        'success' => 'Registro creado exitosamente.'
    ],
    'read' => [
        'record_not_found' => 'El recurso solicitado no fue encontrado.',
        'fields_by_aggregation_disabled' => 'La consulta contiene campos de agregación, pero el agrupamiento (Group By) está deshabilitado.'
    ],
    'update' => [
        'empty_item' => 'No se enviaron datos para actualizar el registro.',
        'error' => 'Error al intentar actualizar el registro en la base de datos.',
        'success' => 'Registro actualizado exitosamente.',
        'no_changes' => 'No se realizaron cambios porque los datos ya estaban actualizados.'
    ],
    'delete' => [
        'error' => 'Error al intentar eliminar el registro de la base de datos.',
        'success' => 'Registro eliminado exitosamente.',
        'success_trash' => 'Registro movido a la papelera exitosamente.'
    ],
    'restore' => [
        'error' => 'Error al intentar restaurar el registro de la papelera.',
        'success' => 'Registro restaurado exitosamente.'
    ]
];
