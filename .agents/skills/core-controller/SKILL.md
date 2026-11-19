---
name: core-controller
description: >
  Reglas, arquitectura y estándares para controladores RESTful del Backend (paquete api/) en api/app/Controllers/V1/.
  Cubre jerarquía de carpetas, docblocks, integración de CrudTraits con DQB (campos
  calculados, niveles de acceso, filtros dinámicos), filtros de autenticación y scope,
  arquitectura de permisos GAC y pre-cálculo en caché, rutas para forks en Custom.php,
  procesos por sucursal en Branches/{Nicho}, y reciclaje de servicios y métodos auxiliares.
---

# Core Boilerplate — Convenciones y Arquitectura de Controladores (`api/`)

Esta guía define los estándares arquitectónicos para controladores RESTful del paquete **`api/`** en `api/app/Controllers/V1/` (rutas internas de CodeIgniter 4 expresadas desde `api/` como `app/Controllers/V1/`).

---

## 1. Jerarquía de Carpetas y Rutas en Forks

Los controladores reflejan los módulos y entidades de la API:

```text
app/Controllers/V1/
├── Modulo/
│   ├── Entidad/
│   │   ├── Home.php                 # CRUD principal (v1/modulo/entidad)
│   │   ├── SubRecurso.php           # Sub-recurso terminal SIN hijos (v1/modulo/entidad/{id}/sub-recurso)
│   │   ├── AccionEspecial.php       # Operaciones RPC o no-CRUD
│   │   └── SubRecursoIntermedio/    # Sub-recurso que posee sus propios sub-recursos
│   │       ├── Home.php             # CRUD del nodo intermedio (v1/modulo/entidad/{id}/sub-recurso)
│   │       └── Hijo.php             # Sub-recurso subordinado (.../sub-recurso/{id}/hijo)
├── Branches/
│   ├── Home.php                     # Árbol global de sucursales (v1/branches)
│   └── {Nicho}/                     # Procesos por sucursal en forks (ej: Clinic, Financing, Pos)
│       └── Entidad/
│           └── Home.php             # Endpoints subordinados (v1/branches/{id}/{nicho}/...)
```

### Convención de Nomenclatura para Recursos y Sub-recursos:
1. **Recurso Raíz del Módulo**: Siempre reside en `Modulo/Entidad/Home.php`.
2. **Sub-recurso Terminal (sin hijos subordinados)**:
   - Se crea como archivo directo dentro de la carpeta del padre: `Modulo/Entidad/SubRecurso.php`.
3. **Sub-recurso con Hijos (nodo intermedio)**:
   - Se organiza como directorio: su CRUD principal reside en `SubRecurso/Home.php` y sus hijos en `SubRecurso/Hijo.php`.

### Reglas de Rutas y Extensiones en Forks:
1. **`app/Config/Routes/V1/Custom.php`**: Toda ruta agregada por un **fork** DEBE registrarse exclusivamente aquí. PROHIBIDO editar `Core.php`.
2. **Procesos por Sucursal (`Branches/{Nicho}`)**: Aplican obligatoriamente el filtro de ámbito `auth:scope=uri|3` (o el segmento URI de la sucursal).
3. **Dualidad RESTful (Unitario y Masivo)**:
   Dentro de `$routes->group('items', ...)`, la cadena vacía `''` representa la raíz del recurso:
   - **`POST ''`**: Creación unitaria (JSON objeto `{}`) o masiva agregando `?is_batch=true` (JSON array `[{}]`).
   - **`PUT / DELETE / PATCH (:num)`**: Operación unitaria recibiendo el ID en la URL.
   - **`PUT / DELETE / PATCH ''`**: Operación masiva sin ID en la URL; el body envía un array de objetos con sus IDs (`[{"id": 1, ...}]`) y retorna `207 Multi-Status`.

```php
$routes->group('items', ['filter' => 'auth:module_code=mod_items'], static function ($routes) {
    $routes->post('',         'Home::create',     ['filter' => 'auth:module_code=mod_items,only_user']);
    $routes->get('',          'Home::index');
    $routes->get('(:num)',    'Home::show/$1');
    // Unitarios (con ID)
    $routes->put('(:num)',    'Home::update/$1',  ['filter' => 'auth:module_code=mod_items,only_user']);
    $routes->delete('(:num)', 'Home::delete/$1',  ['filter' => 'auth:module_code=mod_items,only_user']);
    $routes->patch('(:num)',  'Home::restore/$1', ['filter' => 'auth:module_code=mod_items,only_user']);
    // Masivos (sin ID)
    $routes->put('',          'Home::update',     ['filter' => 'auth:module_code=mod_items,only_user']);
    $routes->delete('',       'Home::delete',     ['filter' => 'auth:module_code=mod_items,only_user']);
    $routes->patch('',        'Home::restore',    ['filter' => 'auth:module_code=mod_items,only_user']);
});
```

---

## 2. Encabezado DocBlock y Ausencia de Comentarios Redundantes

El DocBlock de cabecera debe ser conciso y enfocado en el **propósito de negocio y singularidades** (filosofía Ponytail):

1. **Propósito y Ruta (1 línea)**: Entidad que resuelve (`tabla_mysql`) y URI base.
2. **Singularidades y Efectos Secundarios**: Documentar **únicamente** lo no evidente (invalidación de caché en `after`, notificaciones asíncronas, enmascaramiento de datos, lógica de árbol o metadatos dinámicos).
3. **PROHIBIDO documentar métodos CRUD obvios**: Cero comentarios o viñetas explicando qué hace `create`, `index`, `show`, `update` o `delete`.

#### Caso A: CRUD Estándar Plano (Sin singularidades)
```php
/**
 * Gestión de roles del sistema (`gac_role`) en `v1/access/roles`.
 */
class Home extends BaseController { ... }
```

#### Caso B: CRUD con Singularidades o Efectos Secundarios
```php
/**
 * Gestión de usuarios del sistema (`gac_user`) en `v1/access/users`.
 *
 * - Despacha notificación de bienvenida (`user_welcome`) tras creación exitosa.
 * - Enmascara `password` en bitácora y bloquea mutación directa de credenciales en update.
 * - Invalida la matriz de accesos GAC en caché (`purgeCache`) al actualizar.
 */
class Home extends BaseController { ... }
```

#### Caso C: Acción Puntual / RPC (No-CRUD)
```php
/**
 * Autenticación de usuarios por contraseña en `v1/auth/user/login`.
 *
 * Emite par de tokens JWT (access y refresh), evalúa restricciones de IP/horario,
 * y audita intentos fallidos con bloqueo preventivo.
 */
class Login extends BaseController { ... }
```

---

## 3. Callbacks de `CrudTraits` (`setCallback`)

`CrudTraits` orquesta mutaciones dentro de transacciones SQL ejecutando callbacks en puntos clave:

```php
$this->setCallback('nombreCallback', function(...) { ... });
```

| Callback | Parámetros que recibe | Retorno Permitido | Momento y Efecto en el Flujo |
| :--- | :--- | :--- | :--- |
| `'before'` | `array $input` | `array` \| `Response` \| `void` | Antes de procesar el lote/registro. Retorna `$input` normalizado o `Response` para abortar. |
| `'setRules'` | `array $rules` | `array` \| `Response` \| `void` | Modifica reglas generales del modelo. Retorna `$rules` mutadas o `void`. |
| `'itemSetValues'` | `array $item, array $actionConfig` | `array` \| `Response` \| `void` | Enriquece o inyecta valores antes de validar. Retorna `$item` mutado o `void`. |
| `'itemSetRules'` | `array $rules, array $item, array $actionConfig` | `array` \| `Response` \| `void` | Ajusta reglas dinámicamente para un ítem específico dentro de un lote. |
| `'itemAfterValidation'` | `array $validItem, array $item, array $actionConfig` | `array` \| `Response` \| `void` | Valida el ítem. Retorna `void` (éxito/scoping), `array` (transformación o error 400), o `Response` (abortar). |
| `'itemSetBinnacleData'` | `int\|string $itemId, array $binnacleData, array $actionConfig` | `array` \| `Response` \| `void` | Modifica o enmascara datos en auditoría (`sys_binnacle`). |
| `'itemSuccess'` | `int\|string $itemId, array $item, array $itemResponse, array $actionConfig` | `array` \| `Response` \| `void` | Tras persistir exitosamente cada ítem. Permite mutar la respuesta individual o ejecutar efectos secundarios. |
| `'after'` | `array $response, array $actionConfig` | `void` \| `Response` \| `array` | Se ejecuta **una sola vez** al finalizar la operación. **Lugar canónico para purgar caché** (`if ($response['affected'] > 0)`). |
| `'read:params'` | `array $params, array $config` | `array` \| `Response` \| `void` | Intercepta parámetros DQB (`fields`, `filters`, `order`) antes del SQL. |
| `'read:schema'` | `\DancasDev\DQB\Schema $schema, array $config` | `void` \| `Response` | Permite alterar el objeto Schema en tiempo de ejecución (joins dinámicos). |
| `'read:fieldsCalculated'` | `array $data, array $fieldsCalculatedToAdd, callable $removeFieldsFunction` | `array` \| `Response` | Computa campos virtuales y limpia campos temporales con `$removeFieldsFunction($row)`. |

> **⚡ Reglas de Rendimiento en Callbacks:**
> - **En `itemAfterValidation` retornar `void`:** Si la validación pasó o solo aplicó scoping (`$this->model->where(...)`). Prohibido retornar arrays idénticos sin mutar; fuerza chequeos de error redundantes en bucle.
> - **Cero Consultas N+1:** Prohibido ejecutar consultas SQL individuales dentro de callbacks iterativos (`itemSetValues`, `itemAfterValidation`, `itemSuccess`). Usar consultas previas agrupadas (`whereIn`) o reglas con memoización (`is_not_unique_multi_condition`).

---

## 4. Configuración de `CrudTraits` (`setConfig`)

| Clave | Tipo | Default | Descripción |
| :--- | :--- | :---: | :--- |
| `'limit'` | `int` | `100` | Límite máximo de ítems permitidos en mutaciones masivas. |
| `'messageSuccess'` | `string` | `null` | Mensaje de éxito para operaciones individuales. |
| `'messageSuccessTrash'` | `string` | `null` | Mensaje de éxito al mover a papelera. |
| `'inTrash'` | `bool` | `null` | Si opera sobre papelera (`In-Trash: true`). |
| `'create:isBatch'` | `bool\|null` | `null` | Fuerza comportamiento unitario o masivo. |
| `'read:limit'` | `int` | `255` | Límite superior para `itemsPerPage`. |
| `'read:default'` | `int` | `25` | Registros por página por defecto. |
| `'read:groupByDisabled'` | `bool` | `true` | Bloquea agregaciones sin `groupBy`. |
| `'read:accessLevel'` | `int` | `null` | Nivel de acceso aplicado al Schema DQB (`0`: bajo, `1`: medio, `2`: alto). |
| `'read:filtersDefault'` | `array` | `null` | Filtros WHERE fijos en lecturas (ej: `[['parent_id', $parentId]]`). |
| `'read:havingDefault'` | `array` | `null` | Filtros HAVING fijos en lecturas. |
| `'read:fieldsCalculated'` | `array` | `null` | Mapa de campos virtuales: `$this->schema::FIELDS_CALCULATED`. |

---

## 5. Filtros de Seguridad de la Aplicación (`App\Filters`)

### 1. Filtro `auth` (Sesión, Permisos GAC y Scope)
Agrupa `AuthFilter`, `AuthPermissionFilter` y `AuthRestrictionFilter`.
- **`module_code`**: Código técnico en `gac_module` (soporta pipes `module_code=users|profile`).
- **`only_user`**: Exclusivo para usuarios humanos (`subt: 1`). Rechaza clientes API (401).
- **`only_client`**: Exclusivo para clientes API / M2M (`subt: 2`). Rechaza usuarios (401).
- **`scope`**: Alcance de sucursal en el árbol:
  - `scope=uri|{segmento}` ➔ Extrae ID de la URL (ej: `scope=uri|3`).
  - `scope=param|{nombre}` ➔ Extrae ID de query param (ej: `scope=param|branch_id`).
  - `scope=jwt|{claim}` ➔ Extrae ID de un claim del token.
  - `scope=global` ➔ Ámbito global (`*`).
- **`allow_forced`**: Permite acceso omitiendo acciones forzadas pendientes (ej: `password_change`).

> **Deducción Automática de Acciones:** `AuthPermissionFilter` no requiere parámetro de acción; deduce el permiso por el verbo HTTP: `POST` ➔ `create`, `GET` ➔ `read`, `PUT` ➔ `update`, `DELETE` ➔ `delete`, `PATCH` ➔ `restore` (y exige `trash` si viene `In-Trash: true`).

### 2. Filtros de Transacción Crítica:
- **`otp:action={codigo}`**: Exige `verification_id` y `otp_code` válidos en el body JSON.
- **`action_token:action={nombre}`**: Exige token JWT de un solo uso en header o body.

---

## 6. Motor GAC (Control de Acceso) y Caché

1. Gestionado por `service('authAccess')` (`App\Libraries\GAC\Access`).
2. La matriz de accesos calculada se almacena en caché bajo la clave `gac` con TTL de 600 segundos (10 minutos).
3. **Invalidación obligatoria**: Tras mutar roles, permisos o restricciones, se purga:
   ```php
   service('authAccess')->purgeCacheBy('user', [$userId]);
   service('authAccess')->purgeCacheBy('role', [$roleId]);
   service('authAccess')->purgeCacheBy('client', [$clientId]);
   ```

---

## 7. Funciones Auxiliares Estandarizadas

Los métodos privados se ubican obligatoriamente bajo el delimitador:
```php
    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
```

### 1. `_read($id = null, bool $returnArray = false, ?int $limit = null): Response|array`
Unifica la lectura de `index()` (listado) y `show($id)` (detalle unitario). Configura límites, filtros por defecto y campos calculados antes de invocar `baseRead($id, $returnArray)`.

### 2. `getDirectRelationCriteria(...$parentKeys, bool $isRead = false): array`
Asegura el **scoping directo entre el modelo actual y su padre inmediato**:
- **Soporte para claves compuestas y polimórficas**: Acepta múltiples claves si la relación padre-hijo no es un simple escalar (ej. `entity_type` + `entity_id` o `[parent_id, category_id]`).
- **Retorno Bimodal Obligatorio**:
  - **Lectura (`$isRead = true`)**: Retorna array de pares `[['campo', 'valor'], ...]` para `$this->setConfig('read:filtersDefault', ...)`.
  - **Mutación (`$isRead = false`)**: Retorna mapa asociativo `['campo' => 'valor', ...]` para inyectar en `$this->model->where(...)` dentro de `itemAfterValidation`, `delete` y `restore`.

### 3. `getAncestorRelation(...$ancestorIds, bool $addIsDisabled = true): array|Response`
**ÚNICO nombre permitido** para blindar la jerarquía de URLs con 2 o más niveles de anidación (ej: `branches/{branch_id}/.../{parent_id}/items`).
- **Propósito:** Actúa como *guard clause* al inicio de cada acción pública (`create`, `update`, `delete`, `restore`, `_read`), validando la integridad del árbol aguas arriba antes de cualquier procesamiento.
- **Una Sola Consulta SQL:** Resuelve la cadena mediante `INNER JOIN` desde la tabla del padre directo hacia la raíz. Prohibido ejecutar consultas secuenciales ($N+1$).
- **`LIMIT 1` Obligatorio:** Agregar siempre `->limit(1)` antes de `->get()->getRowArray()`.
- **Proyección Quirúrgica:** Prohibido `SELECT *`. Seleccionar únicamente los IDs validados y las columnas indispensables que el controlador consumirá downstream.
- **Soft-Delete (`deleted_at IS NULL`):** Obligatorio en **todas** las entidades del árbol que manejen borrado lógico.
- **Inhabilitación (`$addIsDisabled`):**
  - Si es `true` (default en mutaciones), exige `is_disabled = '0'` para **todas** las entidades de la cadena que posean la columna.
  - En lecturas (`_read`), se pasa `false` explícitamente para permitir la visualización o auditoría de históricos en entidades inactivas.
- **Error 404 Estándar:** Si el registro no existe, no coinciden las relaciones, o algún ancestro está borrado o inhabilitado, aborta de inmediato:
  ```php
  return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
  ```

#### Patrón Canónico de Implementación:
```php
public function create($branchId = null, $categoryId = null, $parentId = null): Response {
    $ancestor = $this->getAncestorRelation($branchId, $categoryId, $parentId);
    if ($ancestor instanceof Response) {
        return $ancestor;
    }

    $this->setCallback('itemSetValues', function (array $item) use ($parentId): array {
        $item['parent_id'] = $parentId;
        return $item;
    });

    return $this->baseCreate();
}

private function _read($branchId, $categoryId, $parentId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
    $ancestor = $this->getAncestorRelation($branchId, $categoryId, $parentId, false);
    if ($ancestor instanceof Response) {
        return $ancestor;
    }

    if (!empty($limit)) {
        $this->setConfig('limit', $limit);
    }

    $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($parentId, true));

    return $this->baseRead($id, $returnArray);
}

// ---------------------------------------------------------------
//  Funciones auxiliares
// ---------------------------------------------------------------

private function getDirectRelationCriteria(int|string $parentId, bool $isRead = false): array {
    if ($isRead) {
        return [['parent_id', $parentId]];
    }
    return ['parent_id' => $parentId];
}

private function getAncestorRelation(
    int|string $branchId,
    int|string $categoryId,
    int|string $parentId,
    bool $addIsDisabled = true
): array|Response {
    $builder = $this->model->db->table('tbl_parent p')
        ->select('p.id, p.category_id, p.custom_data, c.branch_id')
        ->join('tbl_category c', 'c.id = p.category_id', 'inner')
        ->join('glb_branch b', 'b.id = c.branch_id', 'inner')
        ->where('p.id', $parentId)
        ->where('c.id', $categoryId)
        ->where('b.id', $branchId)
        ->where('p.deleted_at IS NULL')
        ->where('c.deleted_at IS NULL')
        ->where('b.deleted_at IS NULL');

    if ($addIsDisabled) {
        $builder->where('c.is_disabled', '0')
                ->where('b.is_disabled', '0');
    }

    $ancestor = $builder->limit(1)->get()->getRowArray();

    if (!$ancestor) {
        return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
    }

    return $ancestor;
}
```

### 4. `validationOfCompositeKeys(array $item): bool|array`
Valida la unicidad de tuplas compuestas mediante `CompoundKeysValidator` antes de persistir.

### 5. `getMetadata($classifierId): ?Metadata`
**ÚNICO nombre permitido** para instanciar la librería `Metadata` delimitada con `->withScope(['clasificador_id' => $classifierId])` o retornar `null` si no posee esquema.

### 6. `purgeCache(...)`
**ÚNICO nombre permitido** para métodos auxiliares que purguen caché en el controlador tras mutaciones (`if ($response['affected'] > 0)` en callback `'after'`). Prohibido usar `clearCache` o `removeCache`.

```php
public function update($id = null): Response {
    $this->setCallback('after', function (array $response) use ($id): void {
        if ($response['affected'] > 0) {
            $this->purgeCache($id);
        }
    });

    return $this->baseUpdate($id);
}

private function purgeCache($id = null): void {
    (new MiModelo())->removeByCache();
}
```

---

## 8. Cuándo Usar y NO Usar `CrudTraits`

| Escenario | ¿Usar `CrudTraits`? | Justificación |
| :--- | :---: | :--- |
| **Entidades CRUD estándar** (usuarios, sucursales, roles, catálogos) | **SÍ** | Automatiza inserciones, lecturas unificadas, filtros DQB, soft-deletes, transacciones y bitácora. |
| **Flujos RPC o transaccionales** (login, logout, OTP, cambio de password, checkout) | **NO** | Extender directamente de `BaseController` y utilizar `ResponseTrait` (`$this->respond()`, `$this->fail()`). |

---

## 9. Catálogo de Servicios Registrados (`Config\Services`)

| Servicio | Acceso Helper | Propósito |
| :--- | :--- | :--- |
| **Autenticación JWT** | `service('authToken')` | Tokens de sesión principal de usuario y cliente API. |
| **Tokens de Acción** | `service('actionToken')` | Tokens temporales de un solo uso para flujos restringidos. |
| **Gestión de OTPs** | `service('otpManager')` | Generación, despacho y verificación de códigos OTP. |
| **Notificaciones Outbox** | `service('notification')` | Despacho multicanal asíncrono con persistencia en `sys_notification`. |
| **Mensajería Directa** | `service('messaging')` | Mensajes o alertas inmediatas sin persistencia en outbox. |
| **Control de Acceso GAC** | `service('authAccess')` | Matriz de roles y permisos con pre-cálculo en caché. |
| **Almacenamiento** | `service('storage', bool $isPrivate)` | Flysystem público o privado con lockers (`sys_storage_locker`). |
| **Contexto de Petición** | `service('context')` | Metadatos stateless de la petición activa (`RequestContext`). |

---

## 10. Referencias a Ejemplos Canónicos Reales en el Repositorio

Para inspeccionar implementaciones vivas en el código y calcar su arquitectura:

### A. CRUD Estándar Completo con Bitácora y Caché
- **`app/Controllers/V1/Access/Users/Home.php`**: Implementación de referencia para CRUD con callbacks (`itemSetBinnacleData`, `after`), mutaciones con enmascaramiento y purga de caché.

### B. Sub-recurso Simple con Scoping Directo
- **`app/Controllers/V1/Access/Users/Roles.php`**: Sub-recurso terminal de 1 nivel (`users/{id}/roles`). Muestra la implementación canónica de `getDirectRelationCriteria` con clave foránea simple y unicidad con `validationOfCompositeKeys`.

### C. Sub-recurso con Relación Polimórfica / Compuesta
- **`app/Controllers/V1/Access/Users/Restrictions.php`**: Muestra `getDirectRelationCriteria` bimodal para claves compuestas/polimórficas (`entity_type` + `entity_id`).

### D. Sub-recurso Intermedio vs. Terminal
- **`app/Controllers/V1/System/Storage/Lockers/Home.php`**: Nodo intermedio con sub-recursos hijos.
- **`app/Controllers/V1/System/Storage/Lockers/Files.php`**: Sub-recurso terminal subordinado a un locker.

### E. Flujos RPC Transaccionales (Sin `CrudTraits`)
- **`app/Controllers/V1/Auth/User/Login.php`**: Endpoint puntual que extiende directamente de `BaseController` utilizando `ResponseTrait` (`$this->respond()`, `$this->fail()`), rate-limiting y auditoría manual.
