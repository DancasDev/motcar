<?php

namespace App\Controllers\V1\Accounting\Receivables;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Models\Accounting\ReceivableConceptModel;

/**
 * Gestión de conceptos de cobro (`acc_receivable_concept`) en `v1/accounting/receivables/concepts`.
 *
 * Mantenimiento de plantillas descriptivas de cuotas e invalidación de caché (`purgeCache`).
 */
class Concepts extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\ReceivableConceptModel';
    protected $schemaName = 'App\Schemas\Accounting\ReceivableConceptSchema';

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
        (new ReceivableConceptModel())->removeByCache();
    }
}
