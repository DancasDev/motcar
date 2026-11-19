<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;

class MetadataFieldRuleSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('glb_metadata_field_rule');
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

        echo "  Reglas de metadatos insertadas: {$inserted}, actualizadas: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        $paramString = '{"value":{"type":"string","rules":{"required":true}}';
        $paramInt = '{"value":{"type":"int","rules":{"required":true,"min":1}}';
        $paramNumber = '{"value":{"type":"number","rules":{"required":true}}';
        $paramFormat = '{"format":{"type":"string","rules":{"permit_empty":true}}';

        return [
            [
                'code'              => 'required',
                'name'              => ['es' => 'Obligatorio', 'en' => 'Required'],
                'description'       => ['es' => 'El campo es obligatorio.', 'en' => 'The field is required.'],
                'parameters_schema' => null,
                'rule_template'     => 'required',
            ],
            [
                'code'              => 'required_with',
                'name'              => ['es' => 'Requerido con', 'en' => 'Required with'],
                'description'       => ['es' => 'Requerido si existe al menos uno de los campos indicados.', 'en' => 'Required if any of the given fields is present.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'required_with[{value}]',
            ],
            [
                'code'              => 'required_without',
                'name'              => ['es' => 'Requerido sin', 'en' => 'Required without'],
                'description'       => ['es' => 'Requerido si no existe ninguno de los campos indicados.', 'en' => 'Required if none of the given fields is present.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'required_without[{value}]',
            ],
            [
                'code'              => 'differs',
                'name'              => ['es' => 'Diferente de', 'en' => 'Differs from'],
                'description'       => ['es' => 'Debe ser diferente al campo indicado.', 'en' => 'Must be different from the given field.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'differs[{value}]',
            ],
            [
                'code'              => 'equals',
                'name'              => ['es' => 'Igual a', 'en' => 'Equals'],
                'description'       => ['es' => 'Debe ser igual al campo indicado.', 'en' => 'Must be equal to the given field.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'equals[{value}]',
            ],
            [
                'code'              => 'matches',
                'name'              => ['es' => 'Coincide con', 'en' => 'Matches'],
                'description'       => ['es' => 'Debe coincidir con el campo indicado.', 'en' => 'Must match the given field.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'matches[{value}]',
            ],
            [
                'code'              => 'not_equals',
                'name'              => ['es' => 'No igual a', 'en' => 'Not equal to'],
                'description'       => ['es' => 'Debe ser distinto al campo indicado.', 'en' => 'Must not be equal to the given field.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'not_equals[{value}]',
            ],
            [
                'code'              => 'min_length',
                'name'              => ['es' => 'Longitud mínima', 'en' => 'Minimum length'],
                'description'       => ['es' => 'Longitud mínima de caracteres.', 'en' => 'Minimum number of characters.'],
                'parameters_schema' => $paramInt,
                'rule_template'     => 'min_length[{value}]',
            ],
            [
                'code'              => 'max_length',
                'name'              => ['es' => 'Longitud máxima', 'en' => 'Maximum length'],
                'description'       => ['es' => 'Longitud máxima de caracteres.', 'en' => 'Maximum number of characters.'],
                'parameters_schema' => $paramInt,
                'rule_template'     => 'max_length[{value}]',
            ],
            [
                'code'              => 'exact_length',
                'name'              => ['es' => 'Longitud exacta', 'en' => 'Exact length'],
                'description'       => ['es' => 'Longitud exacta de caracteres.', 'en' => 'Exact number of characters.'],
                'parameters_schema' => $paramInt,
                'rule_template'     => 'exact_length[{value}]',
            ],
            [
                'code'              => 'greater_than',
                'name'              => ['es' => 'Mayor que', 'en' => 'Greater than'],
                'description'       => ['es' => 'Debe ser mayor que el valor indicado.', 'en' => 'Must be greater than the given value.'],
                'parameters_schema' => $paramNumber,
                'rule_template'     => 'greater_than[{value}]',
            ],
            [
                'code'              => 'greater_than_equal_to',
                'name'              => ['es' => 'Mayor o igual que', 'en' => 'Greater or equal than'],
                'description'       => ['es' => 'Debe ser mayor o igual que el valor indicado.', 'en' => 'Must be greater or equal than the given value.'],
                'parameters_schema' => $paramNumber,
                'rule_template'     => 'greater_than_equal_to[{value}]',
            ],
            [
                'code'              => 'less_than',
                'name'              => ['es' => 'Menor que', 'en' => 'Less than'],
                'description'       => ['es' => 'Debe ser menor que el valor indicado.', 'en' => 'Must be less than the given value.'],
                'parameters_schema' => $paramNumber,
                'rule_template'     => 'less_than[{value}]',
            ],
            [
                'code'              => 'less_than_equal_to',
                'name'              => ['es' => 'Menor o igual que', 'en' => 'Less or equal than'],
                'description'       => ['es' => 'Debe ser menor o igual que el valor indicado.', 'en' => 'Must be less or equal than the given value.'],
                'parameters_schema' => $paramNumber,
                'rule_template'     => 'less_than_equal_to[{value}]',
            ],
            [
                'code'              => 'in_list',
                'name'              => ['es' => 'En la lista', 'en' => 'In list'],
                'description'       => ['es' => 'Debe estar en la lista de valores (separados por coma).', 'en' => 'Must be in the comma-separated list of values.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'in_list[{value}]',
            ],
            [
                'code'              => 'not_in_list',
                'name'              => ['es' => 'Fuera de la lista', 'en' => 'Not in list'],
                'description'       => ['es' => 'No debe estar en la lista de valores (separados por coma).', 'en' => 'Must not be in the comma-separated list of values.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'not_in_list[{value}]',
            ],
            [
                'code'              => 'alpha',
                'name'              => ['es' => 'Solo letras', 'en' => 'Alpha only'],
                'description'       => ['es' => 'Solo caracteres alfabéticos.', 'en' => 'Alphabetic characters only.'],
                'parameters_schema' => null,
                'rule_template'     => 'alpha',
            ],
            [
                'code'              => 'alpha_space',
                'name'              => ['es' => 'Solo letras y espacios', 'en' => 'Alpha and spaces'],
                'description'       => ['es' => 'Solo caracteres alfabéticos y espacios.', 'en' => 'Alphabetic characters and spaces only.'],
                'parameters_schema' => null,
                'rule_template'     => 'alpha_space',
            ],
            [
                'code'              => 'alpha_dash',
                'name'              => ['es' => 'Letras, guiones y subrayados', 'en' => 'Alpha, dashes, underscore'],
                'description'       => ['es' => 'Letras, números, guiones y subrayados.', 'en' => 'Letters, numbers, dashes and underscores.'],
                'parameters_schema' => null,
                'rule_template'     => 'alpha_dash',
            ],
            [
                'code'              => 'alpha_numeric',
                'name'              => ['es' => 'Solo letras y números', 'en' => 'Alpha-numeric'],
                'description'       => ['es' => 'Solo caracteres alfanuméricos.', 'en' => 'Alphanumeric characters only.'],
                'parameters_schema' => null,
                'rule_template'     => 'alpha_numeric',
            ],
            [
                'code'              => 'alpha_numeric_punct',
                'name'              => ['es' => 'Alfanumérico y puntuación', 'en' => 'Alpha-numeric and punctuation'],
                'description'       => ['es' => 'Solo caracteres alfanuméricos y puntuación.', 'en' => 'Alphanumeric characters and punctuation only.'],
                'parameters_schema' => null,
                'rule_template'     => 'alpha_numeric_punct',
            ],
            [
                'code'              => 'alpha_numeric_space',
                'name'              => ['es' => 'Alfanumérico y espacios', 'en' => 'Alpha-numeric and spaces'],
                'description'       => ['es' => 'Solo caracteres alfanuméricos y espacios.', 'en' => 'Alphanumeric characters and spaces only.'],
                'parameters_schema' => null,
                'rule_template'     => 'alpha_numeric_space',
            ],
            [
                'code'              => 'string',
                'name'              => ['es' => 'Cadena', 'en' => 'String'],
                'description'       => ['es' => 'Debe ser una cadena de texto.', 'en' => 'Must be a string.'],
                'parameters_schema' => null,
                'rule_template'     => 'string',
            ],
            [
                'code'              => 'decimal',
                'name'              => ['es' => 'Decimal', 'en' => 'Decimal'],
                'description'       => ['es' => 'Debe ser un número decimal.', 'en' => 'Must be a decimal number.'],
                'parameters_schema' => null,
                'rule_template'     => 'decimal',
            ],
            [
                'code'              => 'hex',
                'name'              => ['es' => 'Hexadecimal', 'en' => 'Hexadecimal'],
                'description'       => ['es' => 'Debe ser un valor hexadecimal.', 'en' => 'Must be a hexadecimal value.'],
                'parameters_schema' => null,
                'rule_template'     => 'hex',
            ],
            [
                'code'              => 'integer',
                'name'              => ['es' => 'Entero', 'en' => 'Integer'],
                'description'       => ['es' => 'Debe ser un número entero.', 'en' => 'Must be an integer.'],
                'parameters_schema' => null,
                'rule_template'     => 'integer',
            ],
            [
                'code'              => 'is_natural',
                'name'              => ['es' => 'Entero no negativo', 'en' => 'Non-negative integer'],
                'description'       => ['es' => 'Debe ser un número entero mayor o igual a cero.', 'en' => 'Must be an integer greater or equal to zero.'],
                'parameters_schema' => null,
                'rule_template'     => 'is_natural',
            ],
            [
                'code'              => 'is_natural_no_zero',
                'name'              => ['es' => 'Entero positivo', 'en' => 'Positive integer'],
                'description'       => ['es' => 'Debe ser un entero mayor a cero.', 'en' => 'Must be an integer greater than zero.'],
                'parameters_schema' => null,
                'rule_template'     => 'is_natural_no_zero',
            ],
            [
                'code'              => 'numeric',
                'name'              => ['es' => 'Numérico', 'en' => 'Numeric'],
                'description'       => ['es' => 'Debe ser un número.', 'en' => 'Must be a number.'],
                'parameters_schema' => null,
                'rule_template'     => 'numeric',
            ],
            [
                'code'              => 'regex_match',
                'name'              => ['es' => 'Patrón (regex)', 'en' => 'Pattern (regex)'],
                'description'       => ['es' => 'Debe cumplir la expresión regular.', 'en' => 'Must match the regular expression.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'regex_match[{value}]',
            ],
            [
                'code'              => 'timezone',
                'name'              => ['es' => 'Zona horaria', 'en' => 'Timezone'],
                'description'       => ['es' => 'Debe ser una zona horaria válida.', 'en' => 'Must be a valid timezone.'],
                'parameters_schema' => null,
                'rule_template'     => 'timezone',
            ],
            [
                'code'              => 'valid_base64',
                'name'              => ['es' => 'Base64 válido', 'en' => 'Valid base64'],
                'description'       => ['es' => 'Debe ser una cadena base64 válida.', 'en' => 'Must be a valid base64 string.'],
                'parameters_schema' => null,
                'rule_template'     => 'valid_base64',
            ],
            [
                'code'              => 'valid_json',
                'name'              => ['es' => 'JSON válido', 'en' => 'Valid JSON'],
                'description'       => ['es' => 'Debe ser un JSON válido.', 'en' => 'Must be a valid JSON string.'],
                'parameters_schema' => null,
                'rule_template'     => 'valid_json',
            ],
            [
                'code'              => 'valid_email',
                'name'              => ['es' => 'Correo electrónico válido', 'en' => 'Valid email'],
                'description'       => ['es' => 'Debe ser un correo electrónico.', 'en' => 'Must be a valid email address.'],
                'parameters_schema' => null,
                'rule_template'     => 'valid_email',
            ],
            [
                'code'              => 'valid_emails',
                'name'              => ['es' => 'Correos válidos', 'en' => 'Valid emails'],
                'description'       => ['es' => 'Lista de correos electrónicos separados por coma.', 'en' => 'Comma-separated list of valid email addresses.'],
                'parameters_schema' => null,
                'rule_template'     => 'valid_emails',
            ],
            [
                'code'              => 'valid_ip',
                'name'              => ['es' => 'IP válida', 'en' => 'Valid IP'],
                'description'       => ['es' => 'Debe ser una dirección IP válida.', 'en' => 'Must be a valid IP address.'],
                'parameters_schema' => null,
                'rule_template'     => 'valid_ip',
            ],
            [
                'code'              => 'valid_url',
                'name'              => ['es' => 'URL válida', 'en' => 'Valid URL'],
                'description'       => ['es' => 'Debe ser una URL válida.', 'en' => 'Must be a valid URL.'],
                'parameters_schema' => null,
                'rule_template'     => 'valid_url_strict',
            ],
            [
                'code'              => 'valid_url_strict',
                'name'              => ['es' => 'URL válida (estricta)', 'en' => 'Valid URL (strict)'],
                'description'       => ['es' => 'Debe ser una URL válida (estricta).', 'en' => 'Must be a valid URL (strict).'],
                'parameters_schema' => null,
                'rule_template'     => 'valid_url_strict',
            ],
            [
                'code'              => 'valid_date',
                'name'              => ['es' => 'Fecha válida', 'en' => 'Valid date'],
                'description'       => ['es' => 'Debe ser una fecha válida.', 'en' => 'Must be a valid date.'],
                'parameters_schema' => $paramFormat,
                'rule_template'     => 'valid_date[{format}]',
            ],
            [
                'code'              => 'date_greater_than',
                'name'              => ['es' => 'Fecha posterior a', 'en' => 'Date after'],
                'description'       => ['es' => 'Debe ser una fecha posterior a la indicada.', 'en' => 'Must be a date after the specified value.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'date_greater_than[{value}]',
            ],
            [
                'code'              => 'date_greater_than_equal_to',
                'name'              => ['es' => 'Fecha posterior o igual a', 'en' => 'Date after or equal to'],
                'description'       => ['es' => 'Debe ser una fecha posterior o igual a la indicada.', 'en' => 'Must be a date after or equal to the specified value.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'date_greater_than_equal_to[{value}]',
            ],
            [
                'code'              => 'date_less_than',
                'name'              => ['es' => 'Fecha anterior a', 'en' => 'Date before'],
                'description'       => ['es' => 'Debe ser una fecha anterior a la indicada.', 'en' => 'Must be a date before the specified value.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'date_less_than[{value}]',
            ],
            [
                'code'              => 'date_less_than_equal_to',
                'name'              => ['es' => 'Fecha anterior o igual a', 'en' => 'Date before or equal to'],
                'description'       => ['es' => 'Debe ser una fecha anterior o igual a la indicada.', 'en' => 'Must be a date before or equal to the specified value.'],
                'parameters_schema' => $paramString,
                'rule_template'     => 'date_less_than_equal_to[{value}]',
            ],
        ];
    }

    private function getCustomData(): array {
        return [];
    }
}
