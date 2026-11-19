<?php

namespace App\Controllers\V1\Accounting\Payments\Categories;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Models\Accounting\PaymentAccountCategoryModel;
use App\Models\Global\MetadataSchemaModel;

/**
 * Categorías de cuentas de pago (acc_payment_account_category).
 *
 * Define tipos de canales receptores (efectivo, bancos, billeteras) y su metadata_schema_id asociado.
 *
 * - Crear/Actualizar/Eliminar/Restaurar: purga condicional de la caché de categorías.
 * - Lectura: registros enriquecidos mediante PaymentAccountCategorySchema.
 * - Aprovisionamiento 1:1 de Esquema: createSchema vincula/crea el esquema asegurando table_name = 'acc_payment_account'.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\PaymentAccountCategoryModel';
    protected $schemaName = 'App\Schemas\Accounting\PaymentAccountCategorySchema';

    public function create(): Response {
        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
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
        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseUpdate($id);
    }

    public function delete($id = null): Response {
        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseDelete($id);
    }

    public function restore($id = null): Response {
        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseRestore($id);
    }

    /**
     * Aprovisiona de forma controlada e idempotente (1:1) el esquema de metadatos para la categoría.
     * Utiliza la caché para verificar existencia y reinicia la caché al asociar un nuevo esquema.
     */
    public function createSchema($id = null): Response {
        $categories = $this->model->getByCache();

        if (!isset($categories[$id])) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        $schemaId = $categories[$id]['metadata_schema_id'];

        if ($schemaId === null) {
            $schemaId = model(MetadataSchemaModel::class)->insert([
                'code'           => "payment_account_category_{$id}",
                'table_name'     => 'acc_payment_account',
                'storage_column' => 'metadata',
            ]);

            $this->model->update($id, ['metadata_schema_id' => $schemaId]);
            $this->purgeCache();
        }

        return $this->respond([
            'status' => 201,
            'data'   => [
                'category_id'        => $id,
                'metadata_schema_id' => $schemaId,
            ],
        ], 201);
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

    private function purgeCache(): void {
        $this->model->removeByCache();
    }
}
