<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;

class MetadataFieldTypeSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('glb_metadata_field_type');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $row['name'] = json_encode($row['name'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $row['description'] = json_encode($row['description'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $existing = $builder->select('id')->where('code', $row['code'])->limit(1)->get()->getRow();
            if ($existing) {
                $row['updated_at'] = $time;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        echo "  Tipos de metadatos insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'code'             => 'text',
                'name'             => ['es' => 'Texto', 'en' => 'Text'],
                'description'      => ['es' => 'Valor de texto libre (cadenas, nombres, etc.).', 'en' => 'Free-form text value (strings, names, etc.).'],
                'attribute_schema' => $this->json('textSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'textarea',
                'name'             => ['es' => 'Área de texto', 'en' => 'Textarea'],
                'description'      => ['es' => 'Texto multilínea.', 'en' => 'Multi-line text.'],
                'attribute_schema' => $this->json('textareaSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'number',
                'name'             => ['es' => 'Número', 'en' => 'Number'],
                'description'      => ['es' => 'Número entero o decimal.', 'en' => 'Integer or decimal number.'],
                'attribute_schema' => $this->json('numberSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'email',
                'name'             => ['es' => 'Correo electrónico', 'en' => 'Email'],
                'description'      => ['es' => 'Dirección de correo electrónico válida.', 'en' => 'Valid email address.'],
                'attribute_schema' => $this->json('textSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'date',
                'name'             => ['es' => 'Fecha', 'en' => 'Date'],
                'description'      => ['es' => 'Valor de fecha (YYYY-MM-DD).', 'en' => 'Date value (YYYY-MM-DD).'],
                'attribute_schema' => $this->json('dateSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'time',
                'name'             => ['es' => 'Hora', 'en' => 'Time'],
                'description'      => ['es' => 'Valor de hora (HH:MM).', 'en' => 'Time value (HH:MM).'],
                'attribute_schema' => $this->json('timeSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'datetime_local',
                'name'             => ['es' => 'Fecha y hora', 'en' => 'Datetime'],
                'description'      => ['es' => 'Fecha y hora local (YYYY-MM-DDTHH:MM).', 'en' => 'Local date and time (YYYY-MM-DDTHH:MM).'],
                'attribute_schema' => $this->json('timeSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'month',
                'name'             => ['es' => 'Mes', 'en' => 'Month'],
                'description'      => ['es' => 'Mes y año (YYYY-MM).', 'en' => 'Month and year (YYYY-MM).'],
                'attribute_schema' => $this->json('dateSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'week',
                'name'             => ['es' => 'Semana', 'en' => 'Week'],
                'description'      => ['es' => 'Semana del año (YYYY-Www).', 'en' => 'Week of the year (YYYY-Www).'],
                'attribute_schema' => $this->json('dateSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'color',
                'name'             => ['es' => 'Color', 'en' => 'Color'],
                'description'      => ['es' => 'Color en formato hexadecimal (#RRGGBB).', 'en' => 'Color in hexadecimal format (#RRGGBB).'],
                'attribute_schema' => $this->json('stringSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'url',
                'name'             => ['es' => 'URL', 'en' => 'URL'],
                'description'      => ['es' => 'Dirección web (URL) válida.', 'en' => 'Valid web address (URL).'],
                'attribute_schema' => $this->json('textSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'file',
                'name'             => ['es' => 'Archivo', 'en' => 'File'],
                'description'      => ['es' => 'URL de referencia a un archivo subido (firma, logo, documento, etc.).', 'en' => 'Reference URL to an uploaded file (signature, logo, document, etc.).'],
                'attribute_schema' => $this->json('fileSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'tel',
                'name'             => ['es' => 'Teléfono', 'en' => 'Phone'],
                'description'      => ['es' => 'Número de teléfono.', 'en' => 'Phone number.'],
                'attribute_schema' => $this->json('telSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'password',
                'name'             => ['es' => 'Contraseña', 'en' => 'Password'],
                'description'      => ['es' => 'Texto oculto (contraseña).', 'en' => 'Hidden text (password).'],
                'attribute_schema' => $this->json('textSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'range',
                'name'             => ['es' => 'Rango', 'en' => 'Range'],
                'description'      => ['es' => 'Valor numérico dentro de un rango.', 'en' => 'Numeric value within a range.'],
                'attribute_schema' => $this->json('numberSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'select',
                'name'             => ['es' => 'Lista desplegable', 'en' => 'Select'],
                'description'      => ['es' => 'Selección de una opción de una lista.', 'en' => 'Pick one option from a list.'],
                'attribute_schema' => $this->json('selectSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'checkbox',
                'name'             => ['es' => 'Casilla', 'en' => 'Checkbox'],
                'description'      => ['es' => 'Valor booleano (marcado/desmarcado).', 'en' => 'Boolean value (checked/unchecked).'],
                'attribute_schema' => $this->json('checkboxSchema'),
                'is_disabled'      => '0',
            ],
            [
                'code'             => 'radio',
                'name'             => ['es' => 'Opción', 'en' => 'Radio'],
                'description'      => ['es' => 'Selección de una opción de varias.', 'en' => 'Pick one option from several.'],
                'attribute_schema' => $this->json('radioSchema'),
                'is_disabled'      => '0',
            ],
        ];
    }

    private function getCustomData(): array {
        return [];
    }

    private function json(string $method): string {
        return json_encode($this->$method(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function textSchema(): array {
        return [
            'label'       => ['type' => ['string', 'i18n'], 'default' => ''],
            'description' => ['type' => ['string', 'i18n', 'NULL'], 'default' => '', 'rules' => ['max_length' => 120]],
            'placeholder' => ['type' => ['string', 'i18n', 'NULL'], 'default' => ''],
            'default'     => ['type' => ['string', 'NULL']],
            'max_length'  => ['type' => ['int', 'NULL']],
            'readonly'    => ['type' => 'bool', 'default' => false],
        ];
    }

    private function textareaSchema(): array {
        return array_merge($this->textSchema(), [
            'rows' => ['type' => ['int', 'NULL'], 'rules' => ['min' => 1]],
        ]);
    }

    private function numberSchema(): array {
        return [
            'label'       => ['type' => ['string', 'i18n'], 'default' => ''],
            'description' => ['type' => ['string', 'i18n', 'NULL'], 'default' => '', 'rules' => ['max_length' => 120]],
            'placeholder' => ['type' => ['string', 'i18n', 'NULL'], 'default' => ''],
            'default'     => ['type' => ['number', 'NULL']],
            'min'         => ['type' => ['number', 'NULL']],
            'max'         => ['type' => ['number', 'NULL']],
            'step'        => ['type' => ['number', 'NULL'], 'rules' => ['min' => 0]],
            'readonly'    => ['type' => 'bool', 'default' => false],
        ];
    }

    private function stringSchema(): array {
        return [
            'label'    => ['type' => ['string', 'i18n'], 'default' => ''],
            'default'  => ['type' => ['string', 'NULL']],
            'readonly' => ['type' => 'bool', 'default' => false],
        ];
    }

    private function fileSchema(): array {
        return [
            'upload_url'   => [
                'type'  => 'string',
                'rules' => [
                    'required' => true,
                    'pattern'  => '/^https?:\/\/(?:localhost|(?:\d{1,3}\.){3}\d{1,3}|(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}|[a-zA-Z0-9-]+)(?::\d{1,5})?(?:\/[^\s]*)?$/i',
                ],
            ],
            'response_key' => [
                'type'    => 'string',
                'default' => 'url',
                'rules'   => ['required' => true, 'min_length' => 1, 'max_length' => 120],
            ],
            'accept'      => ['type' => ['string', 'NULL']],
            'max_size'    => ['type' => ['int', 'NULL'], 'rules' => ['min' => 1]],
            'multiple'    => ['type' => 'bool', 'default' => false],
            'label'       => ['type' => ['string', 'i18n'], 'default' => ''],
            'description' => ['type' => ['string', 'i18n', 'NULL'], 'default' => '', 'rules' => ['max_length' => 120]],
            'default'     => ['type' => ['string', 'array', 'NULL']],
            'readonly'    => ['type' => 'bool', 'default' => false],
        ];
    }

    private function dateSchema(): array {
        return [
            'label'       => ['type' => ['string', 'i18n'], 'default' => ''],
            'description' => ['type' => ['string', 'i18n', 'NULL'], 'default' => '', 'rules' => ['max_length' => 120]],
            'default'     => ['type' => ['string', 'NULL']],
            'min'         => ['type' => ['string', 'NULL']],
            'max'         => ['type' => ['string', 'NULL']],
            'readonly'    => ['type' => 'bool', 'default' => false],
        ];
    }

    private function timeSchema(): array {
        return array_merge($this->dateSchema(), [
            'step' => ['type' => ['number', 'NULL'], 'rules' => ['min' => 0]],
        ]);
    }

    private function telSchema(): array {
        return array_merge($this->textSchema(), [
            'pattern' => ['type' => ['string', 'NULL']],
        ]);
    }

    private function selectSchema(): array {
        return [
            'label'       => ['type' => ['string', 'i18n'], 'default' => ''],
            'description' => ['type' => ['string', 'i18n', 'NULL'], 'default' => '', 'rules' => ['max_length' => 120]],
            'default'     => ['type' => ['string', 'NULL']],
            'options'     => [
                'type'        => 'array',
                'item_schema' => [
                    'value' => ['type' => 'string', 'rules' => ['required' => true]],
                    'label' => ['type' => ['string', 'i18n'], 'rules' => ['required' => true]],
                ],
            ],
            'multiple' => ['type' => 'bool', 'default' => false],
            'readonly' => ['type' => 'bool', 'default' => false],
        ];
    }

    private function checkboxSchema(): array {
        return [
            'label'       => ['type' => ['string', 'i18n'], 'default' => ''],
            'description' => ['type' => ['string', 'i18n', 'NULL'], 'default' => '', 'rules' => ['max_length' => 120]],
            'default'     => ['type' => 'bool', 'default' => false],
            'readonly'    => ['type' => 'bool', 'default' => false],
        ];
    }

    private function radioSchema(): array {
        return [
            'label'       => ['type' => ['string', 'i18n'], 'default' => ''],
            'description' => ['type' => ['string', 'i18n', 'NULL'], 'default' => '', 'rules' => ['max_length' => 120]],
            'default'     => ['type' => ['string', 'NULL']],
            'options'     => [
                'type'        => 'array',
                'item_schema' => [
                    'value' => ['type' => 'string', 'rules' => ['required' => true]],
                    'label' => ['type' => ['string', 'i18n'], 'rules' => ['required' => true]],
                ],
            ],
            'readonly' => ['type' => 'bool', 'default' => false],
        ];
    }
}
