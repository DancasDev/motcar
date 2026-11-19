<?php

namespace App\Controllers\V1\System\Metadata;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use App\Libraries\Metadata\AttributeValidator;
use App\Libraries\Metadata\RuleValidator;

/**
 * Gestión de campos dinámicos de metadatos (`glb_metadata_schema_field`) en `v1/system/metadata/{schemaId}/fields`.
 *
 * Scoping automático por `metadata_schema_id`, validación dinámica de atributos y reglas, inmutabilidad de código con datos existentes y purga de caché (`purgeCache`).
 */
class Fields extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\MetadataSchemaFieldModel';
    protected $schemaName = 'App\Schemas\Global\MetadataSchemaFieldSchema';

    private ?CompoundKeysValidator $compoundKeysValidator = null;
    private ?AttributeValidator $attributeValidator = null;
    private ?RuleValidator $ruleValidator = null;

    public function create($schemaId = null): Response {
        $this->setCallback('itemSetValues', function ($item) use ($schemaId) {
            $item['metadata_schema_id'] = $schemaId;
            return $item;
        });

        $this->setCallback('itemAfterValidation', function ($item) use ($schemaId) {
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }

            $resAttr = $this->attributeValidator()->process($item);
            if ($resAttr !== true) {
                return [
                    'status'   => 400,
                    'error'    => 'FAIL_VALIDATION',
                    'messages' => $resAttr,
                ];
            }

            $resRules = $this->ruleValidator()->process($item);
            if ($resRules !== true) {
                return [
                    'status'   => 400,
                    'error'    => 'FAIL_VALIDATION',
                    'messages' => $resRules,
                ];
            }

            return $item;
        });

        $this->setCallback('after', function ($response) use ($schemaId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($schemaId);
            }
        });

        return $this->baseCreate();
    }

    public function index($schemaId = null): Response {
        return $this->_read($schemaId);
    }

    public function show($schemaId = null, $id = null): Response {
        return $this->_read($schemaId, $id);
    }

    public function update($schemaId = null, $id = null): Response {
        $this->setCallback('setRules', function ($rules) {
            unset($rules['metadata_schema_id']);
            return $rules;
        });

        $this->setCallback('itemSetValues', function ($item) use ($schemaId) {
            $item['metadata_schema_id'] = $schemaId;
            return $item;
        });

        $this->setCallback('itemAfterValidation', function ($item) use ($schemaId) {
            $itemId = $item['id'];
            $this->model->where($this->getDirectRelationCriteria($schemaId));

            if (array_key_exists('code', $item)) {
                $result = $this->checkCodeModification($itemId, $item);
                if ($result !== true) {
                    return $result;
                }

                $result = $this->validationOfCompositeKeys($item);
                if ($result !== true) {
                    return $result;
                }
            }

            if (array_key_exists('attribute_values', $item)) {
                if (!isset($item['metadata_field_type_id'])) {
                    $row = $this->model->db->table($this->model->table)
                        ->select('metadata_field_type_id')
                        ->where('id', $itemId)
                        ->limit(1)
                        ->get()
                        ->getRowArray();
                    if ($row) {
                        $item['metadata_field_type_id'] = $row['metadata_field_type_id'];
                    }
                }
                $resAttr = $this->attributeValidator()->process($item);
                if ($resAttr !== true) {
                    return [
                        'status'   => 400,
                        'error'    => 'FAIL_VALIDATION',
                        'messages' => $resAttr,
                    ];
                }
            }

            if (array_key_exists('rules', $item)) {
                $resRules = $this->ruleValidator()->process($item);
                if ($resRules !== true) {
                    return [
                        'status'   => 400,
                        'error'    => 'FAIL_VALIDATION',
                        'messages' => $resRules,
                    ];
                }
            }

            unset($item['metadata_schema_id']);

            return $item;
        });

        $this->setCallback('after', function ($response) use ($schemaId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($schemaId);
            }
        });

        return $this->baseUpdate($id);
    }

    public function delete($schemaId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use ($schemaId) {
            $this->model->where($this->getDirectRelationCriteria($schemaId));
        });
        $this->setCallback('after', function ($response) use ($schemaId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($schemaId);
            }
        });
        return $this->baseDelete($id);
    }

    public function restore($schemaId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use ($schemaId) {
            $this->model->where($this->getDirectRelationCriteria($schemaId));
        });
        $this->setCallback('after', function ($response) use ($schemaId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($schemaId);
            }
        });
        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($schemaId = null, $id = null): Response {
        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($schemaId, true));
        $this->setCallback('read:schema', function ($schema) {
            $disabledConf = ['read_disabled' => true, 'order_disabled' => true, 'filter_disabled' => true, 'group_by_disabled' => true];
            $schema->addFieldConfig('metadata_schema_id', $disabledConf);
        });
        return $this->baseRead($id);
    }

    private function getDirectRelationCriteria($schemaId, bool $isRead = false) {
        if ($isRead) {
            return [['metadata_schema_id', $schemaId]];
        }
        return ['metadata_schema_id' => $schemaId];
    }

    private function purgeCache($schemaId): void {
        model(\App\Models\Global\MetadataSchemaModel::class)->removeByCache($schemaId);
    }

    private function attributeValidator(): AttributeValidator {
        return $this->attributeValidator ??= new AttributeValidator();
    }

    private function ruleValidator(): RuleValidator {
        return $this->ruleValidator ??= new RuleValidator();
    }

    private function validationOfCompositeKeys(array $item): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['metadata_schema_id', 'code'],
            pkField: $this->model->primaryKey
        )) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['code' => $this->compoundKeysValidator->getError()],
            ];
        }

        return true;
    }

    private function checkCodeModification(?int $id, array $item): bool|array {
        if ($id === null) {
            return true;
        }

        $current = $this->model->db->table('glb_metadata_schema_field f')
            ->select('f.code, s.table_name, s.storage_column')
            ->join('glb_metadata_schema s', 's.id = f.metadata_schema_id')
            ->where('f.id', $id)
            ->limit(1)
            ->get()
            ->getRowArray();

        if (!$current || $current['code'] === $item['code']) {
            return true;
        }

        $tableName = $current['table_name'];
        $storageCol = $current['storage_column'] ?? 'metadata';
        $oldCode = $current['code'];

        $hasData = $this->model->db->table($tableName)
            ->select('1')
            ->where("JSON_CONTAINS_PATH({$storageCol}, 'one', '$.\"{$oldCode}\"') = 1")
            ->limit(1)
            ->get()
            ->getRow();

        if ($hasData !== null) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['code' => lang('Metadata.cannot_change_code_with_records')],
            ];
        }

        return true;
    }
}
