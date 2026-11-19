<?php

use CodeIgniter\Router\RouteCollection;

// ════════════════════════════════════════════════════════════
//  1. AUTH (Autenticación, Sesiones y Tokens)
// ════════════════════════════════════════════════════════════

$routes->group('v1/auth/', ['namespace' => 'App\Controllers\V1\Auth'], function($routes) {
    /**
     * Usuario (inicio de sesión manual)
     * POST   → crear sesión (login)
     * GET    → obtener datos de la sesión
     * PUT    → renovar sesión (refresh)
     * DELETE → cerrar sesión (logout)
     */
    $routes->group('user/', ['namespace' => 'App\Controllers\V1\Auth\User'], function($routes) {
        $routes->post('', 'Home::create');
        $routes->get('', 'Home::index', ['filter' => ['auth:only_user,allow_forced=password_change;email_verification']]);
        $routes->put('', 'Home::update', ['filter' => ['auth:only_user,allow_forced=*']]);
        $routes->delete('', 'Home::delete', ['filter' => ['auth:only_user,allow_forced=*']]);
        $routes->post('signature', 'Home::saveSignature', ['filter' => ['auth:only_user,allow_forced=*']]);
    });

    /**
     * Cliente (inicio de sesión automático)
     * POST   → crear sesión (login)
     * GET    → obtener datos de la sesión
     * PUT    → renovar sesión (refresh)
     * DELETE → cerrar sesión (logout)
     */
    $routes->group('client/', ['namespace' => 'App\Controllers\V1\Auth\Client'], function($routes) {
        $routes->post('', 'Home::create');
        $routes->get('', 'Home::index', ['filter' => ['auth:only_client']]);
        $routes->put('', 'Home::update', ['filter' => ['auth:only_client']]);
        $routes->delete('', 'Home::delete', ['filter' => ['auth:only_client']]);
    });
});

// ════════════════════════════════════════════════════════════
//  2. ACCESS (Identidad, Gobernanza y Control de Acceso)
// ════════════════════════════════════════════════════════════

$routes->group('v1/access/', ['namespace' => 'App\Controllers\V1\Access'], function($routes) {
    // Gestión de Usuarios
    $routes->group('users/', ['namespace' => 'App\Controllers\V1\Access\Users'], function($routes) {
        $routes->post('', 'Home::create',           ['filter' => ['auth:module_code=acc_users,only_user']]);
        $routes->get('', 'Home::index',             ['filter' => ['auth:module_code=acc_users']]);
        $routes->get('(:num)', 'Home::show/$1',     ['filter' => ['auth:module_code=acc_users']]);
        $routes->put('(:num)', 'Home::update/$1',   ['filter' => ['auth:module_code=acc_users,only_user']]);
        $routes->delete('(:num)', 'Home::delete/$1',['filter' => ['auth:module_code=acc_users,only_user']]);
        $routes->patch('(:num)', 'Home::restore/$1',['filter' => ['auth:module_code=acc_users,only_user']]);
        $routes->put('', 'Home::update',            ['filter' => ['auth:module_code=acc_users,only_user']]);
        $routes->delete('', 'Home::delete',         ['filter' => ['auth:module_code=acc_users,only_user']]);
        $routes->patch('', 'Home::restore',         ['filter' => ['auth:module_code=acc_users,only_user']]);

        $routes->group('(:num)/', function($routes) {
            // Roles del usuario
            $routes->post('roles', 'Roles::create/$1',           ['filter' => ['auth:module_code=acc_user_roles,only_user']]);
            $routes->get('roles', 'Roles::index/$1',             ['filter' => ['auth:module_code=acc_user_roles']]);
            $routes->get('roles/(:num)', 'Roles::show/$1/$2',    ['filter' => ['auth:module_code=acc_user_roles']]);
            $routes->put('roles/(:num)', 'Roles::update/$1/$2',  ['filter' => ['auth:module_code=acc_user_roles,only_user']]);
            $routes->delete('roles/(:num)', 'Roles::delete/$1/$2',  ['filter' => ['auth:module_code=acc_user_roles,only_user']]);
            $routes->patch('roles/(:num)', 'Roles::restore/$1/$2',  ['filter' => ['auth:module_code=acc_user_roles,only_user']]);
            $routes->put('roles', 'Roles::update/$1',            ['filter' => ['auth:module_code=acc_user_roles,only_user']]);
            $routes->delete('roles', 'Roles::delete/$1',            ['filter' => ['auth:module_code=acc_user_roles,only_user']]);
            $routes->patch('roles', 'Roles::restore/$1',            ['filter' => ['auth:module_code=acc_user_roles,only_user']]);

            // Permisos del usuario
            $routes->post('permissions', 'Permissions::create/$1',           ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->get('permissions', 'Permissions::index/$1',             ['filter' => ['auth:module_code=acc_user_permissions']]);
            $routes->get('permissions/(:num)', 'Permissions::show/$1/$2',    ['filter' => ['auth:module_code=acc_user_permissions']]);
            $routes->put('permissions/(:num)', 'Permissions::update/$1/$2',  ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->delete('permissions/(:num)', 'Permissions::delete/$1/$2',  ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->patch('permissions/(:num)', 'Permissions::restore/$1/$2',  ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->put('permissions', 'Permissions::update/$1',            ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->delete('permissions', 'Permissions::delete/$1',            ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->patch('permissions', 'Permissions::restore/$1',            ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);

            // Restricciones del usuario
            $routes->post('restrictions', 'Restrictions::create/$1',           ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->get('restrictions', 'Restrictions::index/$1',             ['filter' => ['auth:module_code=acc_user_permissions']]);
            $routes->get('restrictions/(:num)', 'Restrictions::show/$1/$2',    ['filter' => ['auth:module_code=acc_user_permissions']]);
            $routes->put('restrictions/(:num)', 'Restrictions::update/$1/$2',  ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->delete('restrictions/(:num)', 'Restrictions::delete/$1/$2',  ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->patch('restrictions/(:num)', 'Restrictions::restore/$1/$2',  ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->put('restrictions', 'Restrictions::update/$1',            ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->delete('restrictions', 'Restrictions::delete/$1',            ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
            $routes->patch('restrictions', 'Restrictions::restore/$1',            ['filter' => ['auth:module_code=acc_user_permissions,only_user']]);
        });
    });

    // Gestión de Roles
    $routes->group('roles/', ['namespace' => 'App\Controllers\V1\Access\Roles'], function($routes) {
        $routes->post('', 'Home::create',           ['filter' => ['auth:module_code=acc_roles,only_user']]);
        $routes->get('', 'Home::index',             ['filter' => ['auth:module_code=acc_roles']]);
        $routes->get('(:num)', 'Home::show/$1',     ['filter' => ['auth:module_code=acc_roles']]);
        $routes->put('(:num)', 'Home::update/$1',   ['filter' => ['auth:module_code=acc_roles,only_user']]);
        $routes->delete('(:num)', 'Home::delete/$1',['filter' => ['auth:module_code=acc_roles,only_user']]);
        $routes->patch('(:num)', 'Home::restore/$1',['filter' => ['auth:module_code=acc_roles,only_user']]);
        $routes->put('', 'Home::update',            ['filter' => ['auth:module_code=acc_roles,only_user']]);
        $routes->delete('', 'Home::delete',         ['filter' => ['auth:module_code=acc_roles,only_user']]);
        $routes->patch('', 'Home::restore',         ['filter' => ['auth:module_code=acc_roles,only_user']]);

        // Entidades asignadas al rol (solo lectura)
        $routes->get('(:num)/entities', 'Entities::index/$1',       ['filter' => ['auth:module_code=acc_role_members']]);
        $routes->get('(:num)/entities/(:num)', 'Entities::show/$1/$2', ['filter' => ['auth:module_code=acc_role_members']]);

        // Permisos del rol
        $routes->post('(:num)/permissions', 'Permissions::create/$1',           ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->get('(:num)/permissions', 'Permissions::index/$1',             ['filter' => ['auth:module_code=acc_role_permissions']]);
        $routes->get('(:num)/permissions/(:num)', 'Permissions::show/$1/$2',    ['filter' => ['auth:module_code=acc_role_permissions']]);
        $routes->put('(:num)/permissions/(:num)', 'Permissions::update/$1/$2',  ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->delete('(:num)/permissions/(:num)', 'Permissions::delete/$1/$2',  ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->patch('(:num)/permissions/(:num)', 'Permissions::restore/$1/$2',  ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->put('(:num)/permissions', 'Permissions::update/$1',            ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->delete('(:num)/permissions', 'Permissions::delete/$1',            ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->patch('(:num)/permissions', 'Permissions::restore/$1',            ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);

        // Restricciones del rol
        $routes->post('(:num)/restrictions', 'Restrictions::create/$1',           ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->get('(:num)/restrictions', 'Restrictions::index/$1',             ['filter' => ['auth:module_code=acc_role_permissions']]);
        $routes->get('(:num)/restrictions/(:num)', 'Restrictions::show/$1/$2',    ['filter' => ['auth:module_code=acc_role_permissions']]);
        $routes->put('(:num)/restrictions/(:num)', 'Restrictions::update/$1/$2',  ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->delete('(:num)/restrictions/(:num)', 'Restrictions::delete/$1/$2',  ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->patch('(:num)/restrictions/(:num)', 'Restrictions::restore/$1/$2',  ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->put('(:num)/restrictions', 'Restrictions::update/$1',            ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->delete('(:num)/restrictions', 'Restrictions::delete/$1',            ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
        $routes->patch('(:num)/restrictions', 'Restrictions::restore/$1',            ['filter' => ['auth:module_code=acc_role_permissions,only_user']]);
    });

    // Gestión de Clientes API
    $routes->group('clients/', ['namespace' => 'App\Controllers\V1\Access\Clients'], function($routes) {
        $routes->post('', 'Home::create',           ['filter' => ['auth:module_code=acc_clients,only_user']]);
        $routes->get('', 'Home::index',             ['filter' => ['auth:module_code=acc_clients']]);
        $routes->get('(:num)', 'Home::show/$1',     ['filter' => ['auth:module_code=acc_clients']]);
        $routes->put('(:num)', 'Home::update/$1',   ['filter' => ['auth:module_code=acc_clients,only_user']]);
        $routes->delete('(:num)', 'Home::delete/$1',['filter' => ['auth:module_code=acc_clients,only_user']]);
        $routes->patch('(:num)', 'Home::restore/$1',['filter' => ['auth:module_code=acc_clients,only_user']]);
        $routes->put('', 'Home::update',            ['filter' => ['auth:module_code=acc_clients,only_user']]);
        $routes->delete('', 'Home::delete',         ['filter' => ['auth:module_code=acc_clients,only_user']]);
        $routes->patch('', 'Home::restore',         ['filter' => ['auth:module_code=acc_clients,only_user']]);

        // Roles del cliente
        $routes->post('(:num)/roles', 'Roles::create/$1',           ['filter' => ['auth:module_code=acc_client_roles,only_user']]);
        $routes->get('(:num)/roles', 'Roles::index/$1',             ['filter' => ['auth:module_code=acc_client_roles']]);
        $routes->get('(:num)/roles/(:num)', 'Roles::show/$1/$2',    ['filter' => ['auth:module_code=acc_client_roles']]);
        $routes->put('(:num)/roles/(:num)', 'Roles::update/$1/$2',  ['filter' => ['auth:module_code=acc_client_roles,only_user']]);
        $routes->delete('(:num)/roles/(:num)', 'Roles::delete/$1/$2',  ['filter' => ['auth:module_code=acc_client_roles,only_user']]);
        $routes->patch('(:num)/roles/(:num)', 'Roles::restore/$1/$2',  ['filter' => ['auth:module_code=acc_client_roles,only_user']]);
        $routes->put('(:num)/roles', 'Roles::update/$1',            ['filter' => ['auth:module_code=acc_client_roles,only_user']]);
        $routes->delete('(:num)/roles', 'Roles::delete/$1',            ['filter' => ['auth:module_code=acc_client_roles,only_user']]);
        $routes->patch('(:num)/roles', 'Roles::restore/$1',            ['filter' => ['auth:module_code=acc_client_roles,only_user']]);

        // Permisos del cliente
        $routes->post('(:num)/permissions', 'Permissions::create/$1',           ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->get('(:num)/permissions', 'Permissions::index/$1',             ['filter' => ['auth:module_code=acc_client_permissions']]);
        $routes->get('(:num)/permissions/(:num)', 'Permissions::show/$1/$2',    ['filter' => ['auth:module_code=acc_client_permissions']]);
        $routes->put('(:num)/permissions/(:num)', 'Permissions::update/$1/$2',  ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->delete('(:num)/permissions/(:num)', 'Permissions::delete/$1/$2',  ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->patch('(:num)/permissions/(:num)', 'Permissions::restore/$1/$2',  ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->put('(:num)/permissions', 'Permissions::update/$1',            ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->delete('(:num)/permissions', 'Permissions::delete/$1',            ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->patch('(:num)/permissions', 'Permissions::restore/$1',            ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);

        // Restricciones del cliente
        $routes->post('(:num)/restrictions', 'Restrictions::create/$1',           ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->get('(:num)/restrictions', 'Restrictions::index/$1',             ['filter' => ['auth:module_code=acc_client_permissions']]);
        $routes->get('(:num)/restrictions/(:num)', 'Restrictions::show/$1/$2',    ['filter' => ['auth:module_code=acc_client_permissions']]);
        $routes->put('(:num)/restrictions/(:num)', 'Restrictions::update/$1/$2',  ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->delete('(:num)/restrictions/(:num)', 'Restrictions::delete/$1/$2',  ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->patch('(:num)/restrictions/(:num)', 'Restrictions::restore/$1/$2',  ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->put('(:num)/restrictions', 'Restrictions::update/$1',            ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->delete('(:num)/restrictions', 'Restrictions::delete/$1',            ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
        $routes->patch('(:num)/restrictions', 'Restrictions::restore/$1',            ['filter' => ['auth:module_code=acc_client_permissions,only_user']]);
    });

    // Gestión de Módulos
    $routes->group('modules/', ['namespace' => 'App\Controllers\V1\Access\Modules'], function($routes) {
        $routes->get('', 'Home::index',             ['filter' => ['auth:module_code=acc_modules']]);
        $routes->get('(:num)', 'Home::show/$1',     ['filter' => ['auth:module_code=acc_modules']]);
        $routes->put('(:num)', 'Home::update/$1',   ['filter' => ['auth:module_code=acc_modules,only_user']]);

        $routes->get('categories', 'Categories::index',             ['filter' => ['auth:module_code=acc_modules']]);
        $routes->get('categories/(:num)', 'Categories::show/$1',    ['filter' => ['auth:module_code=acc_modules']]);
        $routes->put('categories/(:num)', 'Categories::update/$1',  ['filter' => ['auth:module_code=acc_modules,only_user']]);
    });
});

// ════════════════════════════════════════════════════════════
//  3. MY (Autoservicio del Usuario Autenticado)
// ════════════════════════════════════════════════════════════

$routes->group('v1/my/', ['namespace' => 'App\Controllers\V1\My'], function($routes) {
    /**
     * Perfil propio
     */
    $routes->get('profile',          'Profile\Home::show',       ['filter' => ['auth:only_user,allow_forced=*']]);
    $routes->put('profile',          'Profile\Home::update',     ['filter' => ['auth:module_code=my_profile,only_user']]);
    $routes->put('profile/password', 'Profile\Password::update', ['filter' => ['auth:only_user,allow_forced=password_change']]);

    /**
     * Mi actividad (bitácoras del usuario autenticado)
     */
    $routes->get('binnacles',        'Binnacles\Home::index',   ['filter' => ['auth:module_code=my_binnacles,only_user']]);
    $routes->get('binnacles/(:num)', 'Binnacles\Home::show/$1', ['filter' => ['auth:module_code=my_binnacles,only_user']]);

    /**
     * Notificaciones del usuario (In-App, canales y dispositivos)
     */
    $routes->group('notifications/', ['namespace' => 'App\Controllers\V1\My\Notifications'], function($routes) {
        // Bandeja In-App (campanita)
        $routes->get('',                       'Home::index',                ['filter' => ['auth:only_user']]);
        $routes->get('unread-count',           'Home::unreadCount',          ['filter' => ['auth:only_user']]);
        $routes->get('(:num)',                 'Home::show/$1',              ['filter' => ['auth:only_user']]);
        $routes->put('(:num)',                 'Home::markRead/$1',          ['filter' => ['auth:only_user']]);

        // Canales de notificación del usuario
        $routes->get('channels',               'Channels\Home::index',       ['filter' => ['auth:only_user']]);
        $routes->put('channels',               'Channels\Home::update',      ['filter' => ['auth:only_user']]);

        // Canal Push (Firebase FCM) y Dispositivos
        $routes->post('push/subscribe',        'Push\Home::subscribe',       ['filter' => ['auth:only_user']]);
        $routes->get('push/devices',           'Push\Home::devices',         ['filter' => ['auth:only_user']]);
        $routes->get('push/status',            'Push\Home::status',          ['filter' => ['auth:only_user']]);
        $routes->put('push/devices/(:num)',    'Push\Home::updateDevice/$1', ['filter' => ['auth:only_user']]);
        $routes->delete('push/devices/(:num)', 'Push\Home::deleteDevice/$1', ['filter' => ['auth:only_user']]);
    });

    /**
     * Vinculación de cuenta Telegram del usuario
     */
    $routes->group('telegram/', ['namespace' => 'App\Controllers\V1\My\Telegram'], function($routes) {
        $routes->get('subscribe',     'Home::subscribe',   ['filter' => ['auth:only_user']]);
        $routes->get('status',        'Home::status',      ['filter' => ['auth:only_user']]);
        $routes->delete('unsubscribe','Home::unsubscribe', ['filter' => ['auth:only_user']]);
    });
});

// ════════════════════════════════════════════════════════════
//  4. BRANCHES (Gestión de Sucursales - Árbol Jerárquico Core)
// ════════════════════════════════════════════════════════════

$routes->group('v1/branches/', ['namespace' => 'App\Controllers\V1\Branches'], function($routes) {
    $routes->get('tree', 'Home::tree',                    ['filter' => ['auth:module_code=cmp_branches']]);
    $routes->post('', 'Home::create',                     ['filter' => ['auth:module_code=cmp_branches,only_user']]);
    $routes->get('', 'Home::index',                       ['filter' => ['auth:module_code=cmp_branches']]);
    $routes->get('(:num)', 'Home::show/$1',               ['filter' => ['auth:module_code=cmp_branches']]);
    $routes->put('(:num)', 'Home::update/$1',             ['filter' => ['auth:module_code=cmp_branches,only_user']]);
    $routes->delete('(:num)', 'Home::delete/$1',          ['filter' => ['auth:module_code=cmp_branches,only_user']]);
    $routes->patch('(:num)', 'Home::restore/$1',          ['filter' => ['auth:module_code=cmp_branches,only_user']]);
    $routes->put('', 'Home::update',                      ['filter' => ['auth:module_code=cmp_branches,only_user']]);
    $routes->delete('', 'Home::delete',                   ['filter' => ['auth:module_code=cmp_branches,only_user']]);
    $routes->patch('', 'Home::restore',                   ['filter' => ['auth:module_code=cmp_branches,only_user']]);
});

// ════════════════════════════════════════════════════════════
//  5. CATALOGS (Catálogos Globales - Solo Lectura)
// ════════════════════════════════════════════════════════════

$routes->group('v1/catalogs/', ['namespace' => 'App\Controllers\V1\Catalogs', 'filter' => ['auth']], function($routes) {
    $routes->group('countries/', ['namespace' => 'App\Controllers\V1\Catalogs\Countries'], function($routes) {
        $routes->get('', 'Home::index');
        $routes->get('(:num)', 'Home::show/$1');
        $routes->get('(:num)/timezones', 'TimeZones::index/$1');
        $routes->get('(:num)/timezones/(:num)', 'TimeZones::show/$1/$2');
    });

    $routes->group('timezones/', ['namespace' => 'App\Controllers\V1\Catalogs\TimeZones'], function($routes) {
        $routes->get('', 'Home::index');
        $routes->get('(:num)', 'Home::show/$1');
    });

    $routes->group('languages/', ['namespace' => 'App\Controllers\V1\Catalogs\Languages'], function($routes) {
        $routes->get('', 'Home::index');
        $routes->get('(:num)', 'Home::show/$1');
    });

    $routes->group('document-types/', ['namespace' => 'App\Controllers\V1\Catalogs\DocumentTypes'], function($routes) {
        $routes->get('', 'Home::index');
        $routes->get('(:num)', 'Home::show/$1');
    });

    $routes->group('notification-types/', ['namespace' => 'App\Controllers\V1\Catalogs\NotificationTypes'], function($routes) {
        $routes->get('', 'Home::index');
        $routes->get('(:num)', 'Home::show/$1');
    });
});

// ════════════════════════════════════════════════════════════
//  6. SYSTEM (Operación, Auditoría, Servicios y Webhooks)
// ════════════════════════════════════════════════════════════

$routes->group('v1/system/', ['namespace' => 'App\Controllers\V1\System'], function($routes) {
    /**
     * Servicios Auxiliares (Password recovery, Email verification, Invitations)
     */
    $routes->group('services/', ['namespace' => 'App\Controllers\V1\System\Services'], function($routes) {
        $routes->group('password/', ['namespace' => 'App\Controllers\V1\System\Services\Password'], function($routes) {
            $routes->post('recovery', 'Recovery::create');
            $routes->put('recovery', 'Recovery::update', ['filter' => ['otp:action=password_recovery']]);
        });

        $routes->group('email/', ['namespace' => 'App\Controllers\V1\System\Services\Email'], function($routes) {
            $routes->post('verification', 'Verification::create', ['filter' => ['auth:only_user,allow_forced=email_verification']]);
            $routes->put('verification', 'Verification::update', ['filter' => ['auth:only_user,allow_forced=email_verification', 'otp:action=email_verification']]);
        });

        $routes->group('invitations', ['namespace' => 'App\Controllers\V1\System\Services\Invitations'], function($routes) {
            $routes->post('', 'Home::create', ['filter' => ['auth:module_code=acc_users,only_user']]);
            $routes->post('(:num)', 'Home::create/$1', ['filter' => ['auth:module_code=acc_users,only_user']]);
            $routes->get('setup', 'Home::index', ['filter' => ['action_token:action=user_invitation']]);
            $routes->put('setup', 'Home::update', ['filter' => ['action_token:action=user_invitation']]);
        });
    });

    /**
     * Bitácoras del Sistema (Auditoría)
     */
    $routes->group('binnacles/', ['namespace' => 'App\Controllers\V1\System\Binnacles'], function($routes) {
        $routes->get('from/entities', 'FromEntities::index', ['filter' => ['auth:module_code=sys_binnacles']]);
        $routes->get('export/(csv|xlsx|xls|ods|pdf)', 'Home::export/$1', ['filter' => ['auth:module_code=sys_binnacles,only_user']]);
        $routes->get('', 'Home::index', ['filter' => ['auth:module_code=sys_binnacles']]);
        $routes->get('(:num)', 'Home::show/$1', ['filter' => ['auth:module_code=sys_binnacles']]);
        $routes->post('', 'Home::create', ['filter' => ['auth:module_code=sys_binnacles']]);
    });

    /**
     * Metadatos del Sistema (Entidades, Grupos, Campos Dinámicos y Esquema de Render)
     */
    $routes->group('metadata/', ['namespace' => 'App\Controllers\V1\System\Metadata'], function($routes) {
        // Catálogos auxiliares de campos (tipos y reglas)
        $routes->group('fields/', ['namespace' => 'App\Controllers\V1\System\Metadata\Fields'], function($routes) {
            $routes->get('types',            'Types::index',     ['filter' => ['auth']]);
            $routes->get('types/(:num)',     'Types::show/$1',   ['filter' => ['auth']]);
            $routes->get('rules',            'Rules::index',     ['filter' => ['auth']]);
            $routes->get('rules/(:num)',     'Rules::show/$1',   ['filter' => ['auth']]);
        });

        // Esquema consolidado para renderizado en frontend
        $routes->get('(:num)/schema',           'Home::schema/$1',  ['filter' => ['auth']]);

        // Grupos de un esquema
        $routes->group('(:num)/groups', function($routes) {
            $routes->post('',           'Groups::create/$1',     ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->get('',            'Groups::index/$1',      ['filter' => ['auth']]);
            $routes->get('(:num)',      'Groups::show/$1/$2',    ['filter' => ['auth']]);
            $routes->put('(:num)',      'Groups::update/$1/$2',  ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->delete('(:num)',   'Groups::delete/$1/$2',  ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->patch('(:num)',    'Groups::restore/$1/$2', ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->put('',            'Groups::update/$1',     ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->delete('',         'Groups::delete/$1',     ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->patch('',          'Groups::restore/$1',    ['filter' => ['auth:module_code=sys_metadata,only_user']]);
        });

        // Campos de un esquema
        $routes->group('(:num)/fields', function($routes) {
            $routes->post('',           'Fields::create/$1',     ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->get('',            'Fields::index/$1',      ['filter' => ['auth']]);
            $routes->get('(:num)',      'Fields::show/$1/$2',    ['filter' => ['auth']]);
            $routes->put('(:num)',      'Fields::update/$1/$2',  ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->delete('(:num)',   'Fields::delete/$1/$2',  ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->patch('(:num)',    'Fields::restore/$1/$2', ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->put('',            'Fields::update/$1',     ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->delete('',         'Fields::delete/$1',     ['filter' => ['auth:module_code=sys_metadata,only_user']]);
            $routes->patch('',          'Fields::restore/$1',    ['filter' => ['auth:module_code=sys_metadata,only_user']]);
        });

        // Esquemas (raíz del dominio metadata - consulta y actualización de unique_keys)
        $routes->get('',         'Home::index',        ['filter' => ['auth']]);
        $routes->get('(:num)',   'Home::show/$1',      ['filter' => ['auth']]);
        $routes->put('(:num)',   'Home::update/$1',    ['filter' => ['auth:module_code=sys_metadata,only_user']]);
        $routes->put('',         'Home::update',       ['filter' => ['auth:module_code=sys_metadata,only_user']]);
    });

    /**
     * Almacenamiento y Archivos (Storage)
     */
    $routes->group('storage/', ['namespace' => 'App\Controllers\V1\System\Storage'], function($routes) {
        $routes->post('lockers', 'Lockers\Home::create', ['filter' => ['auth:module_code=stg_admin,only_user']]);
        $routes->get('lockers', 'Lockers\Home::index', ['filter' => ['auth:module_code=stg_admin']]);
        $routes->get('lockers/(:num)', 'Lockers\Home::show/$1', ['filter' => ['auth:module_code=stg_admin']]);
        $routes->put('lockers/(:num)', 'Lockers\Home::update/$1', ['filter' => ['auth:module_code=stg_admin,only_user']]);
        $routes->put('lockers', 'Lockers\Home::update', ['filter' => ['auth:module_code=stg_admin,only_user']]);
        $routes->group('lockers/(:num)/', ['namespace' => 'App\Controllers\V1\System\Storage\Lockers'], function($routes) {
            $routes->get('files', 'Files::index/$1', ['filter' => ['auth:module_code=stg_admin']]);
            $routes->delete('files/(:any)', 'Files::delete/$1/$2', ['filter' => ['auth:module_code=stg_admin,only_user']]);
        });

        // URLs firmadas y descarga de archivos privados
        $routes->get('download', 'Private\Home::download', ['filter' => ['action_token:action=storage_download']]);
        $routes->get('(.+)/signed-url/(:segment)', 'Private\Home::signedUrl/$1/$2', ['filter' => ['auth:module_code=stg_files']]);

        // Subida de archivos
        $routes->post('(:any)', 'Home::upload/$1', ['filter' => ['auth:module_code=stg_files']]);
    });

    /**
     * Webhooks del Sistema
     */
    $routes->post('webhooks/telegram', 'Webhooks\Telegram::webhook');
});

