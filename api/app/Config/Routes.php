<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Las rutas estan expresadas en: app/Config/Routes/
 * Para configurar los archivos para el enrutado: app/Config/Routing.php
 */

$routes = service('routes');

// Anulación (suplantación) de método
$request = \Config\Services::request();
if ($request->hasHeader('Method-Override')) {
    $request->setMethod($request->getHeaderLine('Method-Override'));
}

