<?php

namespace App\Libraries\Metadata;

use App\Libraries\Validators\SchemaValidator;
use App\Models\Global\MetadataFieldTypeModel;

/**
 * Valida los atributos de render de un campo (`attribute_values`) contra el
 * `attribute_schema` de su tipo de campo (`glb_metadata_field_type`).
 */
class AttributeValidator {
    public function process(array &$item): true|array {
        $typeId = (int) ($item['metadata_field_type_id'] ?? 0);
        $types = model(MetadataFieldTypeModel::class)->getByCache();

        if (!array_key_exists($typeId, $types)) {
            return ['metadata_field_type_id' => lang('Metadata.attributes.invalid_type')];
        }

        $schema = $types[$typeId]['attribute_schema'] ?? null;
        if ($schema === null) {
            $item['attribute_values'] = null;
            return true;
        }

        $values = $item['attribute_values'] ?? [];
        if ($values === null || $values === '') {
            $values = [];
        }

        if (!is_array($values)) {
            return ['attribute_values' => lang('Metadata.attributes.invalid_attribute_values')];
        }

        $validator = new SchemaValidator();
        $result = $validator->validate($values, $schema);

        if (!$result->isValid()) {
            return ['attribute_values' => $result->errors()];
        }

        $item['attribute_values'] = $result->data();
        return true;
    }
}
