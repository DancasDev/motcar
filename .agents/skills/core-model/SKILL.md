---
name: core-model
description: >
  Reglas, arquitectura y optimización para modelos del Backend (paquete api/) en api/app/Models/. Enfatiza
  herencia de BaseModel, alineación 1:1 con tipos de MySQL, validación declarativa
  (is_not_unique_multi_condition, valid_i18n, date_*), campos JSON con hooks explícitos
  encodeJson, y estrategia rigurosa de caché y aligeramiento de carga al servidor.
---

# Core Boilerplate — Convenciones y Optimización de Modelos (`api/`)

Esta guía define cómo construir, estructurar y optimizar modelos del paquete **`api/`** en `api/app/Models/` (rutas internas de CodeIgniter 4 expresadas desde `api/` como `app/Models/`).

---

## 1. Estructura Base y Propiedades

Todo modelo debe extender de `App\Models\BaseModel`:

```php
<?php

namespace App\Models\Modulo;

use App\Models\BaseModel;

class EjemploModel extends BaseModel {
    protected $table         = 'glb_ejemplo';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'code',
        'name',
        'config',
        'is_disabled',
    ];

    // Reglas de validación declarativas (ver sección 2)
    protected $validationRules = [
        // ...
    ];
}
```

### Propiedades Heredadas de `BaseModel` (Prohibido Redefinir si no Cambian):
No redeclares propiedades que ya están configuradas por defecto en `BaseModel` y `CodeIgniter\Model`:

- `$returnType = 'array'` *(heredado de `BaseModel`)*
- `$useSoftDeletes = true` *(heredado de `BaseModel`)*
- `$useTimestamps = true` *(heredado de `BaseModel`)*
- `$dateFormat = 'datetime'` *(heredado de `BaseModel`)*
- `$skipValidation = false` *(heredado de `BaseModel`)*
- `$useAutoIncrement = true` *(heredado de `CodeIgniter\Model`)*
- `$protectFields = true` *(heredado de `CodeIgniter\Model`)*
- `$allowEmptyInserts = false` *(heredado de `CodeIgniter\Model`)*
- `$updateOnlyChanged = true` *(heredado de `CodeIgniter\Model`)*
- `$createdField = 'created_at'`, `$updatedField = 'updated_at'`, `$deletedField = 'deleted_at'` *(heredados de `CodeIgniter\Model`)*

> **Regla Ponytail / DRY:** En un modelo canónico, declara **únicamente** `$table`, `$primaryKey`, `$allowedFields`, `$validationRules` y, de aplicar, `$jsonFields` / hooks o métodos de caché. Solo define propiedades heredadas ante excepciones deliberadas (ej: tablas sin soft deletes como `$useSoftDeletes = false;` o claves primarias compuestas/manuales como `$useAutoIncrement = false;`).

---

## 2. Reglas de Validación (Alineación 1:1 con MySQL)

Los modelos son la **fuente de la verdad**. Cada regla de validación debe coincidir exactamente con el tipo de columna y sus restricciones en la base de datos:

| Tipo / Requisito MySQL | Regla Recomendada | Notas |
| :--- | :--- | :--- |
| `VARCHAR(N)` | `string\|max_length[N]` | En índices únicos bajo `utf8mb4`, limitar a `max_length[191]`. |
| `TINYINT UNSIGNED` | `is_natural\|less_than_equal_to[255]` | Para contadores, minutos o reintentos. |
| `TINYINT(1)` / Flag | `permit_empty\|in_list[0,1]` | Para banderas de estado (`is_disabled`, `is_private`). |
| `ENUM('a','b')` | `in_list[a,b]` | Valores exactos permitidos. |
| `SET('0','1')` | `regex_match[/^(?:[01](?:,[01])*)$/]` | Conjuntos de flags compactos. |
| `JSON` (general) | `permit_empty\|is_array` o `required\|is_array` | Array asociativo que el modelo serializa a JSON string. |
| `JSON` (multilingüe) | `valid_i18n` o `valid_i18n[min_length:1,max_length:N]` | Validación estricta contra `Config\App::$supportedLocales`. |
| Clave Foránea Activa | `is_not_unique_multi_condition[tabla.id,is_disabled:0;deleted_at:IS_NULL]` | **Obligatorio**: Valida existencia de registros activos. Admite valores dinámicos `{campo_payload}` y cuenta con memoización en memoria para eliminar N+1. |
| Clave Única (Update) | `is_unique[tabla.campo,id,{id}]` | Permite conservar el valor actual en actualizaciones. |
| Fecha Formato | `valid_date[Y-m-d]` | Validación estricta del calendario gregoriano. |
| URL Estricta | `valid_url_strict\|max_length[255]` | Valida protocolos y formato de URL seguro. |
| Cronología Relacional | `date_greater_than[{otro_campo}]` | Admite `date_greater_than_equal_to`, `date_less_than`, `date_less_than_equal_to`. |

---

## 3. Serialización Automática de Campos JSON

Si el modelo contiene columnas JSON que se reciben como array asociativo:

1. Declarar los campos en `$jsonFields`:
   ```php
   protected array $jsonFields = ['name', 'config'];
   ```
2. Declarar **explícitamente** los hooks en el modelo:
   ```php
   protected $beforeInsert = ['encodeJson'];
   protected $beforeUpdate = ['encodeJson'];
   ```
   *(Nota: `BaseModel` provee los métodos `encodeJson()` y `encodeJsonBatch()` con `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`)*.

---

## 4. Estrategia de Caché Interna en Modelos (Convención de 2 Funciones)

La caché en modelos reduce el estrés sobre MySQL en catálogos y configuraciones de baja volatilidad de escritura:

### Reglas de Diseño:
1. **Exactamente Dos Funciones por Caché**:
   - `getByCache(bool $reset = false): array`
   - `removeByCache(): void`
   - Prohibido crear funciones públicas intermedias de consulta (`getAll()`, `getMap()`).
   - **Prohibición absoluta del nombre `get()`**: En CI4, `get()` está reservado por el Query Builder (`BaseBuilder::get()`). Sobrescribirlo rompe las consultas.
2. **Cero Redundancia Semántica**: No repetir el nombre de la entidad en el método (`getItemsByCache` en `ItemModel` es redundante; debe ser `getByCache`).
3. **Variante Parametrizada**: Identificadores contextuales van primero, `$reset = false` va al final:
   ```php
   public function getByCache(int $id, bool $reset = false): array;
   public function removeByCache(int $id): void;
   ```
4. **Anti-Stampede Obligatorio**: Si la consulta no arroja registros, **se DEBE guardar un array vacío `[]` en caché** con su TTL para evitar golpear a MySQL en peticiones consecutivas.
5. **Consulta SQL Única**: Prohibido ejecutar bucles N+1 para cargar datos asociados; estructurar una sola consulta SQL con `JOIN`.
6. **Payload Mínimo**: Almacenar únicamente los campos necesarios en memoria (IDs, códigos, nombres, flags).

### Plantilla de Implementación Canónica:

```php
    private const ITEMS_CACHE_KEY = 'db_modulo_items';
    private const ITEMS_CACHE_TTL = 3600;

    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        $key   = self::ITEMS_CACHE_KEY;

        if ($reset) {
            $cache->delete($key);
        }

        $data = $cache->get($key);
        if (!is_array($data)) {
            $records = $this->select('id, code, name, is_disabled')
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->findAll();

            $data = [];
            foreach ($records as $row) {
                $id = (int) $row['id'];
                $data[$id] = [
                    'id'          => $id,
                    'code'        => $row['code'],
                    'name'        => $row['name'],
                    'is_disabled' => $row['is_disabled'],
                ];
            }

            $cache->save($key, $data, self::ITEMS_CACHE_TTL);
        }

        return $data;
    }

    public function removeByCache(): void {
        service('cache')->delete(self::ITEMS_CACHE_KEY);
    }
```

---

## 5. Prioridades de Rendimiento y Optimización SQL

1. **`limit(1)` Obligatorio**: Siempre que se busque un registro único (por `code`, por `id`, o chequeo de unicidad), agregar explícitamente `limit(1)` para detener el escaneo del motor MySQL de inmediato.
2. **`select()` Quirúrgico**: Proyectar únicamente los campos requeridos para la operación (ej: `$builder->select('id')->where(...)`), nunca `SELECT *` de forma descuidada.
3. **Memoización Request-Scoped**: Utilizar arrays en memoria durante la misma petición (ej: `$multiConditionCache` en reglas de validación) para evitar re-consultar registros idénticos en la base de datos.
4. **Arquitectura Stateless**: Cero acumulación de estado entre peticiones; las propiedades de instancia se limpian al terminar el request HTTP o proceso CLI.

---

## 6. Referencias a Ejemplos Canónicos Reales en el Repositorio

Para inspeccionar implementaciones vivas en el código y calcar su arquitectura:

### A. Modelo con Caché Estándar y Serialización JSON
- **`app/Models/Global/LanguageModel.php`**: Implementación canónica del patrón de dos funciones (`getByCache` con anti-stampede y `removeByCache`), `$allowedFields` mínimos y hooks `encodeJson`.

### B. Modelo con Validación Declarativa Avanzada y Memoización
- **`app/Models/Global/MetadataSchemaFieldModel.php`**: Uso de `is_not_unique_multi_condition` con enlace a variables dinámicas del payload (`{metadata_schema_id}`), validación de arrays y serialización explícita.

### C. Modelo de Entidad Jerárquica y Autorreferencial
- **`app/Models/Global/BranchModel.php`**: Modelo con clave foránea autorreferencial (`parent_id`), validación de unicidad compuesta y resolución recursiva de sucursales.

### D. Modelo Inmutable de Alta Transaccionalidad (Sin Soft-Delete)
- **`app/Models/System/BinnacleModel.php`**: Registro inmutable de auditoría con anulación explícita de soft-deletes (`$useSoftDeletes = false`) y métodos auxiliares de contexto.
