<?php

namespace App\Libraries\Metadata;

use App\Libraries\Validators\SchemaValidator;
use App\Models\Global\MetadataFieldRuleModel;

/**
 * Valida la definición de reglas de un campo (`rules`) contra el catálogo `glb_metadata_field_rule`.
 */
class RuleValidator {
    public function process(array &$item): true|array {
        $rules = $item['rules'] ?? null;

        if (empty($rules)) {
            $item['rules'] = null;
            return true;
        }

        if (!is_array($rules) || !array_is_list($rules)) {
            return ['rules' => lang('Metadata.rules.invalid_format')];
        }

        $normalized = [];
        $errors = [];
        $types = model(MetadataFieldRuleModel::class)->getByCache();
        $validator = new SchemaValidator();

        foreach ($rules as $i => $rule) {
            if (!is_array($rule) || !isset($rule['code'])) {
                $errors["rules.{$i}"] = lang('Metadata.rules.invalid_rule');
                continue;
            }

            $code = $rule['code'];
            $type = $types[$code] ?? null;
            if ($type === null) {
                $errors["rules.{$code}"] = lang('Metadata.rules.unknown_rule', [$code]);
                continue;
            }

            $params = $rule['params'] ?? null;
            if ($params !== null && !is_array($params)) {
                $errors["rules.{$code}"] = lang('Metadata.rules.invalid_params', [$code]);
                continue;
            }

            if ($type['parameters_schema'] !== null) {
                $result = $validator->validate($params ?? [], $type['parameters_schema']);
                if (!$result->isValid()) {
                    foreach ($result->errors() as $field => $message) {
                        $errors["rules.{$code}.{$field}"] = $message;
                    }
                    continue;
                }
                $params = $result->data();
            }

            $normalized[] = ['code' => $code, 'params' => $params];
        }

        if (!empty($errors)) {
            return $errors;
        }

        $item['rules'] = $normalized;
        return true;
    }
}
