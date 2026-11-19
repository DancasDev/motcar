<?php

namespace App\Controllers\V1\Catalogs\Persons;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Metadata\Metadata;
use App\Libraries\Validators\CompoundKeysValidator;

/**
 * Catálogo de personas del sistema (glb_person).
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\PersonModel';
    protected $schemaName = 'App\Schemas\Global\PersonSchema';

    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create(): Response {
        $metadata = new Metadata('glb_person');
        $this->setCallback('itemAfterValidation', function (array $item) use ($metadata) {
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }

            return $metadata->process($item);
        });

        return $this->baseCreate();
    }

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    public function update($id = null): Response {
        $metadata = new Metadata('glb_person');
        $this->setCallback('itemAfterValidation', function (array $item) use ($metadata) {
            if (array_key_exists('identification_type', $item) || array_key_exists('identification_value', $item)) {
                $result = $this->validationOfCompositeKeys($item);
                if ($result !== true) {
                    return $result;
                }
            }

            return $metadata->process($item, $item['id']);
        });

        return $this->baseUpdate($id);
    }

    public function delete($id = null): Response {
        return $this->baseDelete($id);
    }

    public function restore($id = null): Response {
        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        return $this->baseRead($id, $returnArray);
    }

    private function validationOfCompositeKeys(array $item): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['identification_type', 'identification_value'],
            pkField: $this->model->primaryKey
        )) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['identification_value' => $this->compoundKeysValidator->getError()],
            ];
        }

        return true;
    }
}
