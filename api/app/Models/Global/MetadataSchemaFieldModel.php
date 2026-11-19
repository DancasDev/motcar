<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class MetadataSchemaFieldModel extends BaseModel {
    protected $table = 'glb_metadata_schema_field';

    protected $allowedFields = [
        'metadata_schema_id', 'metadata_field_type_id', 'metadata_schema_group_id', 'code',
        'attribute_values', 'rules', 'position', 'is_disabled',
    ];

    protected $validationRules = [
        'metadata_schema_id'       => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_metadata_schema.id,is_disabled:0;deleted_at:IS_NULL]',
        'metadata_field_type_id'  => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_metadata_field_type.id,is_disabled:0;deleted_at:IS_NULL]',
        'metadata_schema_group_id' => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_metadata_schema_group.id,metadata_schema_id:{metadata_schema_id};is_disabled:0;deleted_at:IS_NULL]',
        'code'                     => 'required|alpha_dash|max_length[40]',
        'attribute_values'         => 'permit_empty|is_array',
        'rules'                    => 'permit_empty|is_array',
        'position'                 => 'permit_empty|is_natural|less_than_equal_to[99]',
        'is_disabled'              => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['attribute_values', 'rules'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
