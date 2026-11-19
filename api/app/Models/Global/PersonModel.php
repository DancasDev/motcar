<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class PersonModel extends BaseModel {
    protected $table         = 'glb_person';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'identification_type',
        'identification_value',
        'first_name',
        'last_name',
        'phone_primary',
        'phone_secondary',
        'address',
        'city',
        'metadata',
        'is_disabled',
    ];

    protected $validationRules = [
        'identification_type'  => 'required|in_list[0,1,2,3]',
        'identification_value' => 'required|string|max_length[20]',
        'first_name'           => 'required|string|max_length[60]',
        'last_name'            => 'required|string|max_length[60]',
        'phone_primary'        => 'required|string|regex_match[/^\+[1-9][0-9]{7,14}$/]',
        'phone_secondary'      => 'permit_empty|string|regex_match[/^\+[1-9][0-9]{7,14}$/]',
        'address'              => 'permit_empty|string',
        'city'                 => 'permit_empty|string|max_length[80]',
        'metadata'             => 'permit_empty|is_array',
        'is_disabled'          => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['metadata'];
    protected $beforeInsert     = ['encodeJson'];
    protected $beforeUpdate     = ['encodeJson'];
}
