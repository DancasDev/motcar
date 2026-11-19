<?php

namespace App\Libraries\Metadata;

use App\Models\Global\MetadataFieldRuleModel;

/**
 * Valida valores de metadatos/campos dinámicos contra reglas de CodeIgniter 4.
 */
class DataValidator {
    /** @var array<string, array{rules: string[]}> */
    private array $fieldRules = [];

    public function __construct(array $fieldDefinitions = []) {
        if ($fieldDefinitions !== []) {
            $this->setFields($fieldDefinitions);
        }
    }

    /**
     * @param array $fieldDefinitions [{code: string, rules: array|string|null}]
     */
    public function setFields(array $fieldDefinitions): void {
        $this->fieldRules = [];

        foreach ($fieldDefinitions as $field) {
            $code = $field['code'] ?? null;
            if ($code === null || $code === '') {
                continue;
            }
            $this->fieldRules[$code] = ['rules' => $this->compileField($field['rules'] ?? null)];
        }
    }

    public function hasRules(): bool {
        return $this->fieldRules !== [];
    }

    public function validate(array $data): bool {
        if ($this->fieldRules === []) {
            return true;
        }

        $validation = service('validation');
        $validation->reset();
        $validation->setRules($this->fieldRules);

        return $validation->run($data);
    }

    public function getErrors(string $prefix = ''): array {
        $errors = service('validation')->getErrors();

        if ($prefix === '') {
            return $errors;
        }

        $prefixed = [];
        foreach ($errors as $code => $message) {
            $prefixed[$prefix . '.' . $code] = $message;
        }

        return $prefixed;
    }

    public function getValidated(): array {
        return service('validation')->getValidated();
    }

    private function compileField(mixed $rules): array {
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        if (!is_array($rules)) {
            $rules = [];
        }

        $ruleDefinitions = model(MetadataFieldRuleModel::class)->getByCache();
        $ruleList = [];
        $hasRequired = false;

        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['code'])) {
                continue;
            }
            if ($rule['code'] === 'required') {
                $hasRequired = true;
            }

            $ruleDef = $ruleDefinitions[$rule['code']] ?? null;
            $template = $ruleDef['rule_template'] ?? null;
            if ($template === null) {
                continue;
            }

            $params = $rule['params'] ?? [];
            if (!is_array($params)) {
                $params = [];
            }

            if (empty($params['format'])) {
                $template = str_replace(['[{format}]', '{format}'], '', $template);
            }

            foreach ($params as $key => $value) {
                if ($value !== null && $value !== '') {
                    $template = str_replace('{' . $key . '}', (string) $value, $template);
                }
            }

            $ruleList[] = trim($template);
        }

        if (!$hasRequired) {
            array_unshift($ruleList, 'permit_empty');
        }

        return $ruleList;
    }
}
