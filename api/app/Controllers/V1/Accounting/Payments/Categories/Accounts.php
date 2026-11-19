<?php

namespace App\Controllers\V1\Accounting\Payments\Categories;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Metadata\Metadata;
use App\Models\Accounting\PaymentAccountModel;
use App\Models\Accounting\PaymentAccountCategoryModel;

/**
 * Cuentas receptoras de pago (acc_payment_account).
 *
 * Sub-recurso subordinado a Categorías (v1/accounting/payments/categories/{category_id}/accounts).
 *
 * - Crear: inyecta category_id desde URI y procesa metadata dinámica con scope por categoría.
 * - Actualizar: scoped a category_id, impide alterar categoría y revalida metadata con scope.
 * - Leer: registros planos filtrados por categoría (o transversal si no se especifica).
 * - Eliminar/Restaurar: soft-delete scoped a la categoría.
 * - Caché: purga condicional en after cuando affected > 0.
 */
class Accounts extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\PaymentAccountModel';
    protected $schemaName = 'App\Schemas\Accounting\PaymentAccountSchema';

    public function create($categoryId = null): Response {
        $metadata = $this->getMetadata($categoryId);

        $this->setCallback('itemSetValues', function (array $item) use ($categoryId): array {
            $item['category_id'] = $categoryId;
            return $item;
        });

        $this->setCallback('setRules', function (array $rules) use ($metadata): ?array {
            if ($metadata === null) {
                unset($rules['metadata']);
                return $rules;
            }
            return null;
        });

        $this->setCallback('itemAfterValidation', function (array $item) use ($metadata): mixed {
            if ($metadata !== null) {
                return $metadata->process($item);
            }
            return null;
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseCreate();
    }

    public function index($categoryId = null): Response {
        return $this->_read($categoryId);
    }

    public function show($categoryId = null, $id = null): Response {
        return $this->_read($categoryId, $id);
    }

    public function update($categoryId = null, $id = null): Response {
        $metadata = $this->getMetadata($categoryId);

        $this->setCallback('itemSetValues', function (array $item): array {
            unset($item['category_id']);
            return $item;
        });

        $this->setCallback('setRules', function (array $rules) use ($metadata): ?array {
            if ($metadata === null) {
                unset($rules['metadata']);
                return $rules;
            }
            return null;
        });

        $this->setCallback('itemAfterValidation', function (array $item) use ($categoryId, $metadata, $id): mixed {
            $this->model->where($this->getDirectRelationCriteria($categoryId));

            if ($metadata !== null) {
                return $metadata->process($item, $item['id'] ?? $id);
            }
            return null;
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseUpdate($id);
    }

    public function delete($categoryId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function () use ($categoryId): void {
            $this->model->where($this->getDirectRelationCriteria($categoryId));
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseDelete($id);
    }

    public function restore($categoryId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function () use ($categoryId): void {
            $this->model->where($this->getDirectRelationCriteria($categoryId));
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($categoryId = null, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        if ($categoryId !== null) {
            $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($categoryId, true));
        }

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria($categoryId, bool $isRead = false): array {
        if ($isRead) {
            return [['category_id', $categoryId]];
        }

        return ['category_id' => $categoryId];
    }

    private function getMetadata($categoryId): ?Metadata {
        $categories = model(PaymentAccountCategoryModel::class)->getByCache();
        $schemaId   = $categories[$categoryId]['metadata_schema_id'] ?? null;

        return $schemaId !== null
            ? (new Metadata($schemaId))->withScope(['category_id' => $categoryId])
            : null;
    }

    private function purgeCache(): void {
        $this->model->removeByCache();
    }
}
