<?php

return [
    'input' => [
        'limit_exceeded' => 'The number of items to be processed exceeds the limit of {0} records per request.',
        'duplicate_pk' => 'The batch contains duplicate identifiers.'
    ],
    'model' => [
        'is_undefined' => 'The action cannot be performed because the model is not defined correctly in the controller.',
        'delete_field_is_required' => 'The action cannot be performed because the field that marks the logical deletion of records in the table is not defined in the model.',
    ],
    'schema' => [
        'is_undefined' => 'The action cannot be performed because the schema is not correctly defined in the controller.',
    ],
    'binnacle' => [
        'error' => 'Error trying to register the action in the binnacle.'
    ],
    'create' => [
        'error' => 'Error trying to create a record in the database.',
        'success' => 'Record created successfully.'
    ],
    'read' => [
        'record_not_found' => 'The requested resource was not found.',
        'fields_by_aggregation_disabled' => 'The query contains aggregation fields, but grouping (Group By) is disabled.'
    ],
    'update' => [
        'empty_item' => 'No data was provided to update the record.',
        'error' => 'Error trying to update the record in the database.',
        'success' => 'Record updated successfully.',
        'no_changes' => 'No changes were made because the data was already up to date.'
    ],
    'delete' => [
        'error' => 'Error trying to delete the record from the database.',
        'success' => 'Record deleted successfully.',
        'success_trash' => 'Record moved to trash successfully.'
    ],
    'restore' => [
        'error' => 'Error trying to restore the record from the trash.',
        'success' => 'Record restored successfully.'
    ]
];
