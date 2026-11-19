<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Filters\BaseTraits;

class AuthPermissionFilter implements FilterInterface {
    use BaseTraits;

    protected array $actionList = [
        'GET'    => 'read',
        'POST'   => 'create',
        'PUT'    => 'update',
        'DELETE' => 'delete',
        'PATCH'  => 'restore',
    ];

    public function __construct() {
        $this->setting = [
            'module_code' => null,
            'scope'       => null,
        ];
    }

    public function before(RequestInterface $request, $arguments = null) {
        if ($request->is('options')) {
            return null;
        }

        $this->initSetting($arguments);
        $authToken = service('authToken');
        $authAccess = service('authAccess');
        $action = strtoupper($request->getMethod());
        $action = $this->actionList[$action] ?? $action;
        $inTrash = $request->getHeaderLine('In-Trash') === 'true';
        $subt = $authToken->getPayloadValue('subt');
        $sub = $authToken->getPayloadValue('sub');

        if (empty($this->setting['module_code']) || empty($subt) || empty($sub)) {
            return null;
        }

        $selectedPermission = null;
        try {
            $scopePath = service('context')->get('auth:scopePath', '*');
            $authAccess->setEntity($subt, $sub)->setScope($scopePath);
            $permissionsSet = $authAccess->getPermissions(true);

            $candidateModules = explode('|', (string) $this->setting['module_code']);
            foreach ($candidateModules as $mod) {
                $mod = trim($mod);
                $perm = $permissionsSet->get($mod);
                if ($perm === null) {
                    continue;
                }

                // Evaluar si el módulo cubre la acción HTTP solicitada
                $hasAction = match (true) {
                    $action === 'restore' => $perm->hasFeature(['update', 'trash']),
                    $inTrash && in_array($action, ['read', 'update', 'delete'], true) => $perm->hasFeature([$action, 'trash']),
                    default => $perm->hasFeature($action),
                };

                if ($hasAction) {
                    $selectedPermission = $perm;
                    break;
                }

                // Fallback al primer módulo existente si aún no se ha seleccionado ninguno
                $selectedPermission ??= $perm;
            }

            if ($selectedPermission === null) {
                return $this->fail(lang('AuthFilter.permision.refused', [$this->setting['module_code']]), 403, 'AUTH_PERMISSION_REFUSED');
            }
        } catch (\Exception $e) {
            return $this->fail(lang('AuthFilter.permision.load_exception'), 500, 'AUTH_PERMISSION_LOAD_EXCEPTION');
        }

        if ($selectedPermission->moduleIsDeveloping() && !$selectedPermission->hasFeature('dev')) {
            return $this->fail(lang('AuthFilter.permision.module_is_developing', [$this->setting['module_code']]), 403, 'AUTH_PERMISSION_MODULE_IS_DEVELOPING');
        }

        $isAuthorized = match (true) {
            $action === 'restore' => $selectedPermission->hasFeature(['update', 'trash']),
            $inTrash && in_array($action, ['read', 'update', 'delete'], true) => $selectedPermission->hasFeature([$action, 'trash']),
            default => $selectedPermission->hasFeature($action),
        };

        if (!$isAuthorized) {
            return $this->fail(lang('AuthFilter.permision.refused_action'), 403, 'AUTH_PERMISSION_REFUSED_ACTION');
        }

        service('context')
            ->set('auth:permission', $selectedPermission)
            ->set('request:action', $action)
            ->set('request:inTrash', $inTrash);

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
