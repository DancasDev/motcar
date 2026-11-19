<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class NoteCategoryModel extends BaseModel {
    protected $table         = 'glb_note_category';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'name',
        'description',
        'color',
        'is_disabled',
    ];

    protected $validationRules = [
        'name'        => 'required|string|max_length[50]|is_unique[glb_note_category.name,id,{id}]',
        'description' => 'permit_empty|string|max_length[255]',
        'color'       => 'permit_empty|string|max_length[7]|regex_match[/^#[0-9A-Fa-f]{6}$/]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];
}
