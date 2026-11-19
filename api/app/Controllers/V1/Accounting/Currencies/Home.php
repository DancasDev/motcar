<?php

namespace App\Controllers\V1\Accounting\Currencies;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Models\Accounting\CurrencyModel;

/**
 * Catálogo de monedas (acc_currency).
 *
 * Definición a nivel global (v1/accounting/currencies).
 *
 * - Crear: valida código ISO y campos del modelo. Purga caché si hubo inserción.
 * - Actualizar: revalida unicidad del código. Purga caché si hubo cambios reales.
 * - Leer: registros planos mediante CurrencySchema.
 * - Eliminar/Restaurar: soft-delete estándar con purga condicional de caché.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\CurrencyModel';
    protected $schemaName = 'App\Schemas\Accounting\CurrencySchema';

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
