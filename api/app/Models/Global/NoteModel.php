<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class NoteModel extends BaseModel {
    protected $table         = 'glb_note';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'note_category_id',
        'author_id',
        'origin_type',
        'origin_id',
        'content',
        'is_disabled',
    ];

    protected $validationRules = [
        'note_category_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_note_category.id,is_disabled:0;deleted_at:IS_NULL]',
        'author_id'        => 'required|is_natural_no_zero|is_not_unique_multi_condition[gac_user.id,is_disabled:0;deleted_at:IS_NULL]',
        'origin_type'      => 'required|in_list[0]',
        'origin_id'        => 'required|is_natural_no_zero',
        'content'          => 'required|string',
        'is_disabled'      => 'permit_empty|in_list[0,1]',
    ];
}
