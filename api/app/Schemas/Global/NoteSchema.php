<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class NoteSchema extends Schema {
    protected array $tablesConfig = [
        'note' => [
            'name' => 'glb_note',
        ],
        'category' => [
            'name'       => 'glb_note_category',
            'dependency' => 'note_category_id',
            'join'       => ['type' => 'LEFT', 'on' => 'note.note_category_id = category.id'],
        ],
        'author' => [
            'name'       => 'gac_user',
            'dependency' => 'author_id',
            'join'       => ['type' => 'LEFT', 'on' => 'note.author_id = author.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                => [],
        'note_category_id'  => [],
        'category_name'     => ['table' => 'category', 'name' => 'name'],
        'category_color'    => ['table' => 'category', 'name' => 'color'],
        'author_id'         => [],
        'author_first_name' => ['table' => 'author', 'name' => 'first_name'],
        'author_last_name'  => ['table' => 'author', 'name' => 'last_name'],
        'origin_type'       => [],
        'origin_id'         => [],
        'content'           => [],
        'is_disabled'       => [],
        'created_at'        => [],
        'updated_at'        => [],
        'deleted_at'        => ['access_level' => 2],
    ];
}
