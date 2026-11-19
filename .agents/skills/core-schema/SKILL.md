---
name: core-schema
description: >
  Convenciones para crear o actualizar esquemas de entidad del Backend (paquete api/) en api/app/Schemas/
  utilizando el motor DancasDev\DQB\Schema. Cubre tablesConfig (joins y dependencias),
  fieldsConfig (alias, columnas, access_level), soporte nativo para campos
  calculados (FIELDS_CALCULATED), y validación automática de configuración.
---

# Core Boilerplate — Convenciones de Schemas (DQB) (`api/`)

Esta guía define cómo construir, estructurar y mantener esquemas de entidad del paquete **`api/`** en `api/app/Schemas/` (rutas internas de CodeIgniter 4 expresadas desde `api/` como `app/Schemas/`) para su consumo con `DancasDev\DQB` y `CrudTraits`.

---

## 1. Propósito y Arquitectura Base

Todo esquema reside en `app/Schemas/` y **DEBE extender directamente de `DancasDev\DQB\Schema`**:

```php
<?php

namespace App\Schemas\Modulo;

use DancasDev\DQB\Schema;

class RecursoSchema extends Schema {
    // 1. Configuración de tablas (primaria y joins condicionales)
    protected array $tablesConfig = [ ... ];

    // 2. Configuración de campos proyectables y niveles de acceso
    protected array $fieldsConfig = [ ... ];
}
```

Los esquemas actúan como la capa declarativa de proyección de datos:
- Definen qué campos y tablas están expuestos para lectura (`fields`), filtros (`filters`), agrupamiento (`groupBy`), ordenamiento (`order`) y agregaciones (`having`).
- Controlan la visibilidad de datos confidenciales mediante niveles de acceso (`access_level`).
- Evitan inyecciones de columnas no autorizadas o joins innecesarios en la base de datos.

---

## 2. Configuración de Tablas (`$tablesConfig`)

Define las tablas involucradas en las consultas del recurso:

```php
    protected array $tablesConfig = [
        'recurso' => [
            'name' => 'glb_recurso', // Tabla primaria (la primera del array)
        ],
        'categoria' => [
            'name'       => 'glb_categoria',
            'dependency' => 'categoria_id', // Clave foránea en la tabla primaria
            'join'       => ['type' => 'LEFT', 'on' => 'recurso.categoria_id = categoria.id'],
        ],
    ];
```

### Reglas de `$tablesConfig`:
1. **Tabla Primaria**: La **primera clave** declarada en el array es tratada automáticamente como la tabla primaria (`getPrimaryTable()`).
2. **Tablas Secundarias (Joins)**:
   - **`name`**: Nombre físico de la tabla en MySQL.
   - **`dependency`**: Clave foránea en la tabla primaria. Si la petición no solicita campos de esta tabla secundaria ni filtra por ella, DQB **omite el JOIN**, optimizando drásticamente la consulta.
   - **`join`**: Array asociativo con `'type'` (`'LEFT'`, `'INNER'`, `'RIGHT'`) y `'on'` con la condición de enlace SQL.
3. **Restricciones de Tabla (opcionales)**:
   - `access_level`: Nivel mínimo requerido para la tabla completa (default `0`).
   - `read_disabled`, `filter_disabled`, `order_disabled`, `group_by_disabled`: Banderas booleanas para deshabilitar capacidades sobre toda la tabla.

---

## 3. Configuración de Campos (`$fieldsConfig`)

Define las columnas accesibles desde la API:

```php
    protected array $fieldsConfig = [
        'id'             => [],
        'code'           => [],
        'name'           => [],
        'categoria_id'   => [],
        'categoria_name' => ['table' => 'categoria', 'name' => 'name'],
        'is_disabled'    => [],
        'created_at'     => [],
        'updated_at'     => [],
        'deleted_at'     => ['access_level' => 2],
    ];
```

### Propiedades de Configuración:
| Opción | Tipo | Default | Descripción |
| :--- | :--- | :---: | :--- |
| `table` | `string` | *(primaria)* | Alias de la tabla a la que pertenece la columna (según `$tablesConfig`). |
| `name` | `string` | *(clave del array)* | Nombre real en BD si difiere del alias expuesto en la API. |
| `access_level` | `int` | `0` | **Nivel de Acceso**: `0` = bajo, `1` = medio, `2` = alto. Si el nivel fijado (`$schema->setAccessLevel($n)`) es menor, el campo se excluye de la consulta y del resultado. |
| `read_disabled` | `bool` | `false` | Impide seleccionar el campo en `fields`. |
| `filter_disabled` | `bool` | `false` | Impide usar el campo en la cláusula `filters`. |
| `order_disabled` | `bool` | `false` | Impide ordenar por este campo en `order`. |
| `group_by_disabled` | `bool` | `false` | Impide agrupar por este campo en `groupBy`. |

### Convención de Niveles de Acceso (`access_level`):
- **`0` (Bajo)**: Campos generales y de acceso base (identificadores, nombres, códigos, descripciones, fechas públicas y estados).
- **`1` (Medio)**: Campos con restricción media.
- **`2` (Alto)**: Campos de acceso alto o confidenciales (auditoría interna como `deleted_at`, contadores de fallos, IPs de login y datos sensibles).

---

## 4. Campos Calculados (Virtuales)

Para campos computados que no existen como columnas directas en MySQL, se declara el patrón canónico de DQB en el Schema:

```php
<?php

namespace App\Schemas\System;

use DancasDev\DQB\Schema;

class BinnacleSchema extends Schema {
    protected array $tablesConfig = [
        'binnacle' => ['name' => 'sys_binnacle'],
    ];

    protected array $fieldsConfig = [
        'id'               => [],
        'from_entity_type' => [],
        'from_entity_id'   => [],
        'action'           => [],
        'date'             => [],
    ];

    // 1. Declarar dependencias requeridas para computar el campo virtual
    public const FIELDS_CALCULATED = [
        'description' => ['from_entity_type', 'from_entity_id', 'action'],
    ];

    // 2. Callback estático que enriquece los registros y limpia campos temporales
    public static function fieldsCalculatedCallback(array $records, array $fieldsCalculated, callable $removeFieldsFunction): array {
        foreach ($records as $key => $item) {
            if (in_array('description', $fieldsCalculated, true)) {
                $records[$key]['description'] = self::formatAction($item);
            }
            // Elimina dependencias que fueron solicitadas solo para el cálculo
            $records[$key] = $removeFieldsFunction($records[$key]);
        }

        return $records;
    }
}
```

### Integración en el Controlador (`_read`):
```php
private function _read($id = null, bool $returnArray = false): Response|array {
    $this->setConfig('read:fieldsCalculated', $this->schema::FIELDS_CALCULATED);
    $this->setCallback('read:fieldsCalculated', $this->schema::fieldsCalculatedCallback(...));

    return $this->baseRead($id, $returnArray);
}
```

---

## 5. Validación Automática en Desarrollo

En entornos de desarrollo (`isDeveloping`), `CrudTraits` invoca automáticamente:
```php
$this->schema->validateConfig();
```
Esto valida en tiempo de carga que:
1. Las tablas secundarias referencien una tabla primaria existente.
2. Cada campo en `$fieldsConfig` apunte a una tabla registrada en `$tablesConfig`.
3. No existan tipos inválidos de datos en las configuraciones, lanzando `SchemaTableConfigBuildException` o `SchemaFieldConfigBuildException` ante cualquier anomalía.

---

## 6. Referencias a Ejemplos Canónicos Reales en el Repositorio

Para inspeccionar implementaciones vivas en el código y calcar su arquitectura:

### A. Schema Completo con Joins Condicionales y Niveles de Acceso
- **`app/Schemas/Gac/UserSchema.php`**: Implementación canónica con múltiples tablas secundarias dependientes (`dependency` en `country_id`, `language_id`, `time_zone_id`) y campos restringidos con `access_level => 2` (`failed_attempt_count`, `last_login_ip`, `deleted_at`).

### B. Schema con Campos Calculados Virtuales (FIELDS_CALCULATED)
- **`app/Schemas/System/BinnacleSchema.php`**: Declaración de dependencias temporales en `FIELDS_CALCULATED`, cómputo estático en `fieldsCalculatedCallback` y purga de columnas auxiliares mediante `$removeFieldsFunction`.

### C. Schema de Entidad Jerárquica y Autorreferencial
- **`app/Schemas/Global/BranchSchema.php`**: Enlace a la misma tabla para resolver nombres de sucursal padre (`parent_name`) y exposición de banderas de estado.

### D. Schema de Almacenamiento y Recursos del Sistema
- **`app/Schemas/System/StorageLockerSchema.php`**: Configuración de lockers con niveles de acceso base y deshabilitación selectiva de capacidades de agrupamiento o filtros.
