<?php

namespace App\Libraries\Metadata;

use App\Models\Global\MetadataSchemaModel;

/**
 * Fachada y orquestador principal para la gestión y validación de metadatos/campos dinámicos.
 */
class Metadata {
    private ?string $tableName = null;
    private ?string $storageColumn = null;
    private array $uniqueKeys = [];
    private array $disabledFields = [];
    private array $scope = [];
    private ?DataValidator $dataValidator = null;

    public function __construct(int|string|null $identifier = null) {
        if ($identifier !== null) {
            $this->forSchema($identifier);
        }
    }

    /**
     * Configura la instancia para operar con un esquema específico (por ID numérico o código).
     */
    public function forSchema(int|string $identifier): self {
        $this->tableName      = null;
        $this->storageColumn  = null;
        $this->uniqueKeys     = [];
        $this->disabledFields = [];
        $this->scope          = [];
        $this->dataValidator  = null;

        $model = model(MetadataSchemaModel::class);

        if (is_numeric($identifier)) {
            $schemaId = (int) $identifier;
        } else {
            $row = $model->select('id')->where('code', $identifier)->where('deleted_at', null)->first();
            $schemaId = $row ? (int) $row['id'] : null;
        }

        if ($schemaId !== null) {
            $schema = $model->getByCache($schemaId);
            if ($schema !== null) {
                $this->tableName     = $schema['table_name'];
                $this->storageColumn = $schema['storage_column'] ?? 'metadata';
                $this->uniqueKeys    = $schema['unique_keys'] ?? [];

                $activeFields = [];
                foreach ($schema['fields'] as $code => $field) {
                    if ($field['disabled']) {
                        $this->disabledFields[] = $code;
                    } else {
                        $activeFields[] = ['code' => $code, 'rules' => $field['rules']];
                    }
                }

                $this->dataValidator = new DataValidator($activeFields);
            }
        }

        return $this;
    }

    /**
     * Define condiciones de ámbito sobre columnas de la tabla física (ej: ['category_id' => 5]).
     * Estas condiciones se aplican directamente a la validación de unicidad de claves únicas.
     */
    public function withScope(array $scope): self {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Procesa y valida el ítem en el callback `itemAfterValidation`.
     * Retorna array con error si falla, o el array del ítem mutado si es válido.
     */
    public function process(array $item, ?int $currentId = null): array {
        if ($this->storageColumn === null || $this->dataValidator === null) {
            return $item;
        }

        $column = $this->storageColumn;

        // Si la columna no está presente en el ítem recibido, no interferir
        if (!array_key_exists($column, $item)) {
            return $item;
        }

        $raw = $item[$column];

        // Normalizar entrada (null, vacío o string JSON)
        if ($raw === null || $raw === '' || $raw === []) {
            $data = [];
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : null;
        } elseif (is_array($raw)) {
            $data = $raw;
        } else {
            $data = null;
        }

        if ($data === null) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => [$column => lang('Validation.valid_json', ['field' => $column])],
            ];
        }

        // Si el esquema no tiene reglas activas configuradas
        if (!$this->dataValidator->hasRules()) {
            $validated = [];
        } else {
            // Ejecutar validación de tipos y formatos sobre campos activos
            if (!$this->dataValidator->validate($data)) {
                return [
                    'status'   => 400,
                    'error'    => 'FAIL_VALIDATION',
                    'messages' => $this->dataValidator->getErrors($column),
                ];
            }
            $validated = $this->dataValidator->getValidated();
        }

        // Si es una actualización y existen campos inhabilitados en el catálogo,
        // preservar sus valores originales almacenados en la base de datos (inmutables por API)
        if ($currentId !== null && !empty($this->disabledFields)) {
            $existingStorage = db_connect()->table($this->tableName)
                ->select($column)
                ->where('id', $currentId)
                ->limit(1)
                ->get()
                ->getRowArray();

            $existingData = [];
            if (!empty($existingStorage[$column])) {
                $rawDb = $existingStorage[$column];
                $existingData = is_array($rawDb) ? $rawDb : (json_decode($rawDb, true) ?: []);
            }

            foreach ($this->disabledFields as $disabledCode) {
                if (array_key_exists($disabledCode, $existingData)) {
                    $validated[$disabledCode] = $existingData[$disabledCode];
                } else {
                    unset($validated[$disabledCode]);
                }
            }
        }

        // Validar unicidad compuesta o simple según configuración del esquema
        if (!empty($this->uniqueKeys)) {
            $uniqueError = $this->checkUniqueKeys($validated, $currentId);
            if ($uniqueError !== null) {
                return [
                    'status'   => 400,
                    'error'    => 'FAIL_VALIDATION',
                    'messages' => $uniqueError,
                ];
            }
        }

        // Almacenar el array saneado y tipado de vuelta en el ítem (el modelo se encarga de encodeJson)
        $item[$column] = empty($validated) ? null : $validated;

        return $item;
    }

    /**
     * Valida la unicidad simple y compuesta para las claves configuradas en el esquema.
     */
    private function checkUniqueKeys(array $validated, ?int $currentId = null): ?array {
        if (empty($this->uniqueKeys) || empty($this->tableName)) {
            return null;
        }

        $db = db_connect();

        foreach ($this->uniqueKeys as $tuple) {
            if (!is_array($tuple) || empty($tuple)) {
                continue;
            }

            // Verificar si todos los campos de la clave única tienen un valor no nulo ni vacío en el payload
            $tupleData = [];
            $allPresent = true;
            foreach ($tuple as $field) {
                if (!array_key_exists($field, $validated) || $validated[$field] === null || $validated[$field] === '') {
                    $allPresent = false;
                    break;
                }
                $tupleData[$field] = $validated[$field];
            }

            if (!$allPresent) {
                continue;
            }

            $lastField = end($tuple);
            $msg = count($tuple) === 1
                ? lang('Metadata.unique', [$lastField])
                : lang('Metadata.unique_composite');

            // Consulta en base de datos con operador nativo JSON
            $builder = $db->table($this->tableName)
                ->select('1')
                ->where('deleted_at', null);

            if ($currentId !== null) {
                $builder->where('id !=', $currentId);
            }

            if (!empty($this->scope)) {
                $builder->where($this->scope);
            }

            foreach ($tupleData as $field => $val) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                    continue;
                }
                $builder->where("JSON_UNQUOTE(JSON_EXTRACT({$this->storageColumn}, '$.{$field}'))", (string) $val);
            }

            $existing = $builder->limit(1)->get()->getRow();
            if ($existing !== null) {
                return ["{$this->storageColumn}.{$lastField}" => $msg];
            }
        }

        return null;
    }
}
