<?php

namespace App\Controllers\V1\System\Metadata;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Gestión de esquemas de metadatos (`glb_metadata_schema`) en `v1/system/metadata`.
 *
 * Configuración de claves únicas compuestas y compilación jerárquica de estructura dinámica para frontend (`schema`).
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\MetadataSchemaModel';
    protected $schemaName = 'App\Schemas\Global\MetadataSchemaSchema';

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    public function update($id = null): Response {
        $this->setCallback('setRules', function ($rules) {
            unset($rules['code'], $rules['table_name'], $rules['storage_column']);
            return $rules;
        });

        $this->setCallback('itemAfterValidation', function ($item) {
            return $this->validateUniqueKeys($item);
        });

        return $this->baseUpdate($id);
    }

    /**
     * Endpoint consolidado para el frontend:
     * GET /v1/system/metadata/{id}/schema
     *
     * Devuelve directamente los mapas indexados de 'groups' y 'fields' (activos):
     * {
     *   "data": {
     *     "groups": { "personal_info": { "title": {...}, "description": {...}, "position": 1 } },
     *     "fields": { "dni": { "group": "personal_info", "type": "text", "attribute_values": {...}, "rules": [...], "position": 1 } }
     *   }
     * }
     */
    public function schema($id = null): Response {
        $schema = $this->model->getByCache((int) $id);

        if (empty($schema)) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        return $this->respond([
            'data' => [
                'groups' => $schema['groups'],
                'fields' => array_filter($schema['fields'], fn($f) => !$f['disabled']),
            ],
        ]);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($id = null): Response {
        return $this->baseRead($id);
    }

    /**
     * Valida que unique_keys sea una lista de listas de cadenas: [["field_a", "field_b"], ...].
     */
    private function validateUniqueKeys(array $item): array
    {
        if (!array_key_exists('unique_keys', $item)) {
            return $item;
        }

        $keys = $item['unique_keys'];

        if (empty($keys)) {
            $item['unique_keys'] = null;
            return $item;
        }

        if (!is_array($keys)) {
            return $this->formatUniqueKeysError();
        }

        $normalized = [];
        foreach ($keys as $tuple) {
            if (!is_array($tuple) || empty($tuple)) {
                return $this->formatUniqueKeysError();
            }

            $validTuple = [];
            foreach ($tuple as $field) {
                if (!is_string($field) || trim($field) === '') {
                    return $this->formatUniqueKeysError();
                }
                $validTuple[] = trim($field);
            }
            $normalized[] = array_values(array_unique($validTuple));
        }

        $item['unique_keys'] = $normalized;
        return $item;
    }

    private function formatUniqueKeysError(): array
    {
        return [
            'status'   => 400,
            'error'    => 'FAIL_VALIDATION',
            'messages' => [
                'unique_keys' => lang('Metadata.unique_keys_invalid_format'),
            ],
        ];
    }
}
