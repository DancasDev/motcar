<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class MetadataSchemaGroupModel extends BaseModel {
    protected $table = 'glb_metadata_schema_group';

    protected $allowedFields = [
        'metadata_schema_id', 'code', 'title', 'description', 'position', 'is_disabled',
    ];

    protected $validationRules = [
        'metadata_schema_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_metadata_schema.id,is_disabled:0;deleted_at:IS_NULL]',
        'code'               => 'required|alpha_dash|max_length[60]',
        'title'              => 'required|valid_i18n[min_length:1,max_length:120]',
        'description'        => 'permit_empty|valid_i18n[max_length:255]',
        'position'           => 'permit_empty|is_natural|less_than_equal_to[99]',
        'is_disabled'        => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['title', 'description'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
