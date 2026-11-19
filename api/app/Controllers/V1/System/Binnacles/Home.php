<?php

namespace App\Controllers\V1\System\Binnacles;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Controllers\Traits\ExportTraits;

/**
 * Auditoría del sistema (sys_binnacle).
 *
 * Permite consultar el historial de acciones y registrar eventos del lado cliente.
 * Soporta exportación masiva en formatos CSV, XLSX, XLS, ODS y PDF.
 */
class Home extends BaseController {
    use CrudTraits, ExportTraits;

    protected $modelName = 'App\Models\System\BinnacleModel';
    protected $schemaName = 'App\Schemas\System\BinnacleSchema';
    protected $format = null;

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    public function create(): Response {
        $this->setCallback('itemSetValues', function ($data) {
            $data['from_entity_type'] = '3';
            $data['from_entity_id']   = null;
            $data['session_id']       = null;
            if (!isset($data['date'])) {
                $data['date'] = date('Y-m-d H:i:s');
            }
            return $data;
        });
        return $this->baseCreate();
    }

    public function export($format = 'csv'): Response {
        $result = $this->_read(null, true, 10000);
        if ($result instanceof Response) {
            return $result;
        }
        $data = $result['data'] ?? [];

        $this->setConfig('exportHeaders', [
            'id', 'date', 'from_entity_type', 'from_entity_id',
            'to_entity_type', 'to_entity_id', 'action', 'ip', 'data', 'session_id', 'description',
        ]);

        $this->setCallback('resolveHeaderMap', function ($dataHeaders) {
            $raw = @json_decode($this->request->getGet('headers'), true);
            if (empty($raw) || !is_array($raw)) {
                return false;
            }

            return $raw;
        });

        $this->setConfig('sheetName', lang('Binnacle.export_sheet_name'));

        return $this->exportToDownload($data, 'binnacle_export.' . $format, [
            'title'    => lang('Binnacle.export_title'),
            'subtitle' => lang('Binnacle.export_subtitle', [count($data), $result['count']]),
        ]);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        $limit ??= 100;
        $this->setConfig('read:limit', $limit);
        $this->setConfig('read:groupByDisabled', true);
        $this->setConfig('read:fieldsCalculated', $this->schema::FIELDS_CALCULATED);
        $this->setCallback('read:fieldsCalculated', $this->schema::fieldsCalculatedCallback(...));

        return $this->baseRead($id, $returnArray);
    }
}