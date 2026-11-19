<?php

namespace App\Controllers\V1\My\Profile;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Metadata\Metadata;

/**
 * Perfil del usuario autenticado (autoservicio).
 *
 * Ruta base: v1/my/profile
 *
 * - Leer: consulta los datos del usuario autenticado vía claim sub del JWT.
 * - Actualizar: actualiza información personal del usuario autenticado,
 *   bloqueando credenciales y banderas internas del sistema.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\UserModel';
    protected $schemaName = 'App\Schemas\Gac\UserSchema';

    public function show($id = null) {
        $userId = service('authToken')->getPayloadValue('sub');

        return $this->baseRead((int) $userId);
    }

    public function update($id = null) {
        $userId = service('authToken')->getPayloadValue('sub');

        // Bloquear campos que no deben ser actualizados directamente desde el perfil
        $this->setCallback('setRules', function($rules) {
            unset($rules['password'], $rules['force_to'], $rules['notification_channels'], $rules['is_disabled']);
            return $rules;
        });

        $metadata = new Metadata('gac_user');
        $this->setCallback('itemAfterValidation', function ($item) use ($metadata) {
            return $metadata->process($item, (int) $item['id']);
        });

        return $this->baseUpdate((int) $userId);
    }
}