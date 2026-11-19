<?php

namespace App\Controllers\V1\Catalogs\NotificationTypes;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Tipos de notificación (sys_notification_type) — catálogo global de solo lectura.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\System\NotificationTypeModel';
    protected $schemaName = 'App\Schemas\System\NotificationTypeSchema';

    public function index(): Response {
        return $this->baseRead();
    }

    public function show($id = null): Response {
        return $this->baseRead($id);
    }
}
