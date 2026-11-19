---
name: core-migration-seeder
description: >
  Convenciones para crear migraciones y seeders del Backend (paquete api/) en api/app/Database/. Enfatiza
  nomenclatura de fechas, tipos canónicos MySQL (VARCHAR 191, JSON nativo, SET, TINYINT),
  claves foráneas con restricciones explícitas, y seeders autocontenidos e idempotentes
  compatibles con forks mediante getBaseData() y getCustomData().
---

# Core Boilerplate — Convenciones de Migraciones y Seeders (`api/`)

Esta guía define las reglas técnicas para estructurar migraciones y seeders del paquete **`api/`** en `api/app/Database/` (rutas internas de CodeIgniter 4 expresadas desde `api/` como `app/Database/`).

---

## 1. Migraciones (`app/Database/Migrations/`)

- **Nomenclatura**: `YYYY-MM-DD-HHMMSS_NombreClase.php` (prefijo timestamp exacto).
- **Tipos de Columna Canónicos en MySQL**:
  - `BIGINT UNSIGNED AUTO_INCREMENT`: Clave primaria `id`.
  - `VARCHAR(191)`: Para campos con índices únicos o compuestos en `utf8mb4`.
  - `JSON`: Para campos multilingües (`name`, `description`) o configuraciones dinámicas (`config`, `payload_schema`).
  - `TINYINT(1) DEFAULT 0`: Para banderas booleanas (`is_disabled`, `is_private`).
  - `TINYINT UNSIGNED`: Para contadores pequeños, minutos o reintentos (`max_retries`).
  - `SET('0','1')`: Para conjuntos fijos de flags compactos (`scope`).
  - Columnas de auditoría estándar:
    - `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
    - `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
    - `deleted_at DATETIME NULL DEFAULT NULL`

### Plantilla de Migración Canónica:
```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbEjemploTable extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'branch_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 191, 'unique' => true],
            'name'        => ['type' => 'JSON'],
            'description' => ['type' => 'JSON', 'null' => true],
            'is_disabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME'],
            'updated_at'  => ['type' => 'DATETIME'],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('glb_ejemplo', true, [
            'ENGINE' => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void {
        $this->forge->dropTable('glb_ejemplo', true);
    }
}
```

---

## 2. Seeders (`app/Database/Seeds/`)

- **Autocontenidos**: **PROHIBIDO** usar `lang()` o depender de archivos en `app/Language/`.
- **Datos i18n nativos**: Textos multilingües declarados directamente como arrays asociativos (`['es' => '...', 'en' => '...']`) codificados a JSON con `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
- **Claves técnicas estables (`code`)**:
  - Para enlazar entidades relacionadas, consultar la base de datos por su `'code'` con `select('id')->limit(1)` en lugar de asumir IDs numéricos hardcodeados.
- **Idempotencia Obligatoria**: Si el registro existe (`limit(1)` por `code`), se actualiza (`update`); si no existe, se inserta (`insert`).

### Patrón de Compatibilidad para Forks (`getBaseData` vs `getCustomData`):
Todo seeder de catálogo o configuración separa los registros base de los del fork para permitir actualizaciones desde upstream sin conflictos:

```php
<?php

namespace App\Database\Seeds\Modulo;

use CodeIgniter\Database\Seeder;

class EjemploSeeder extends Seeder {
    public function run(): void {
        $builder  = $this->db->table('glb_ejemplo');
        $time     = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated  = 0;

        // Combinar datos del Core con los personalizados del Fork
        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $row['name'] = json_encode($row['name'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (isset($row['description'])) {
                $row['description'] = json_encode($row['description'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $existing = $builder->select('id')
                ->where('code', $row['code'])
                ->limit(1)
                ->get()
                ->getRow();

            if ($existing) {
                $row['updated_at'] = $time;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        echo "  Ejemplos insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    /**
     * Datos base del CORE del boilerplate.
     */
    private function getBaseData(): array {
        return [
            [
                'code' => 'registro_core',
                'name' => ['es' => 'Nombre Base', 'en' => 'Base Name'],
            ],
        ];
    }

    /**
     * Datos personalizados del FORK.
     * Los forks agregan registros aquí sin alterar getBaseData().
     */
    private function getCustomData(): array {
        return [
            // [ 'code' => 'registro_fork', ... ]
        ];
    }
}
```

---

## 3. Referencias a Ejemplos Canónicos Reales en el Repositorio

Para inspeccionar implementaciones vivas en el código y calcar su arquitectura:

### A. Migraciones Relacionales con Claves Foráneas e Índices
- **`app/Database/Migrations/2026-06-22-190004_CreateGlbBranch.php`**: Tabla jerárquica con clave foránea a sí misma (`parent_id`), índice único compuesto (`['parent_id', 'name']`), motor InnoDB, charset utf8mb4 y soft-delete.
- **`app/Database/Migrations/2026-08-06-000004_CreateGlbMetadataSchema.php`**: Migración con campos JSON nativos (`unique_keys`), columna de estado `ENUM('0','1')` e índice único en `code`.
- **`app/Database/Migrations/2026-07-12-100000_CreateStorageLockersTable.php`**: Migración con claves compuestas de texto, banderas booleanas múltiples y límites numéricos.

### B. Seeders Idempotentes con Dualidad Base / Custom y Multilingüe (i18n)
- **`app/Database/Seeds/Global/DocumentTypeSeeder.php`**: Implementación canónica de seeder idempotente con `getBaseData()` / `getCustomData()`, multilingüe nativo con `json_encode` y búsqueda previa por clave técnica `code` con `limit(1)`.
- **`app/Database/Seeds/Global/LanguageSeeder.php`**: Seeder de catálogo base del sistema con actualización/inserción condicional.
- **`app/Database/Seeds/Global/MetadataSchemaFieldSeeder.php`**: Seeder relacional con resolución de IDs padre mediante consulta previa por código técnico sin hardcodear identificadores numéricos.
