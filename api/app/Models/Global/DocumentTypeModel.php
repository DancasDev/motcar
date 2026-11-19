<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class DocumentTypeModel extends BaseModel {
    protected $table = 'glb_document_type';

    protected $allowedFields = [
        'code', 'name', 'description', 'width', 'height', 'is_disabled',
    ];

    protected $validationRules = [
        'code'        => 'required|string|max_length[40]|is_unique[glb_document_type.code,id,{id}]',
        'name'        => 'required|valid_i18n[min_length:1,max_length:100]',
        'description' => 'permit_empty|valid_i18n[max_length:255]',
        'width'       => 'required|decimal|greater_than[0]',
        'height'      => 'required|decimal|greater_than[0]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name', 'description'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
