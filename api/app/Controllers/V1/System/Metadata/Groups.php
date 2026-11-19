<?php

namespace App\Controllers\V1\System\Metadata;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;

/**
 * Gestión de grupos visuales de metadatos (`glb_metadata_schema_group`) en `v1/system/metadata/{schemaId}/groups`.
 *
 * Scoping automático por `metadata_schema_id`, unicidad compuesta de código y purga de caché de esquema (`purgeCache`).
 */
class Groups extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\MetadataSchemaGroupModel';
    protected $schemaName = 'App\Schemas\Global\MetadataSchemaGroupSchema';

    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($schemaId = null): Response {
        $this->setCallback('itemSetValues', function ($item) use ($schemaId) {
            $item['metadata_schema_id'] = $schemaId;
            return $item;
        });

        $this->setCallback('itemAfterValidation', function ($item) {
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }
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

        $this->setCallback('itemAfterValidation', function ($item) use ($schemaId) {
            $this->model->where($this->getDirectRelationCriteria($schemaId));

            if (array_key_exists('code', $item)) {
                $result = $this->validationOfCompositeKeys(array_merge($item, ['metadata_schema_id' => $schemaId]));
                if ($result !== true) {
                    return $result;
                }
            }
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
}
