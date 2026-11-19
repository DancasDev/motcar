<?php

namespace App\Libraries\Validators;

use CodeIgniter\Database\ConnectionInterface;

/**
 * CompoundKeysValidator
 *
 * Verifica que una combinación de 1 a N campos sea única en una tabla.
 * Equivalencia de BaseModel::checkCompoundKeys() pero como clase
 * independiente, sin depender del modelo.
 *
 * Uso típico (desde item_after_validation):
 *   $validator = new CompoundKeysValidator();
 *   if (!$validator->validate($item, 'gac_module',
 *       ['module_category_id', 'name'], 'id')) {
 *       return ['status' => 400, 'error' => 'FAIL_VALIDATION',
 *               'messages' => ['name' => $validator->getError()]];
 *   }
 */
class CompoundKeysValidator
{
    private ?string $error = null;
    private ?ConnectionInterface $db = null;
    private ?string $dbGroup = null;

    private function getDbConnection(?string $dbGroup = null): ConnectionInterface
    {
        if ($this->db === null || $this->dbGroup !== $dbGroup) {
            $this->db = db_connect($dbGroup);
            $this->dbGroup = $dbGroup;
        }

        return $this->db;
    }

    /**
     * Ejecuta la validación de unicidad compuesta sobre columnas de tabla.
     *
     * @param array      $item     Datos validados del registro.
     * @param string     $table    Nombre de la tabla (ej: 'gac_module').
     * @param array      $fields   Campos que componen la restricción (1 a N).
     * @param string|null $pkField Columna PK (ej: 'id'). null = create, string = update.
     * @param string|null $dbGroup Grupo de BD (null = default).
     *
     * @return bool true si la combinación es única, false si ya existe.
     */
    public function validate(
        array $item,
        string $table,
        array $fields,
        ?string $pkField = null,
        ?string $dbGroup = null
    ): bool {
        $this->error = null;

        if ($fields === []) {
            return true;
        }

        // Extraer valores del item para cada field
        $criteria = array_intersect_key($item, array_flip($fields));

        // Si es update y faltan campos, completar desde BD
        $isUpdate = $pkField !== null && array_key_exists($pkField, $item);
        $db = $this->getDbConnection($dbGroup);

        if ($isUpdate && count($criteria) < count($fields)) {
            $row = $db
                ->table($table)
                ->select(implode(',', $fields))
                ->where($pkField, $item[$pkField])
                ->limit(1)
                ->get()
                ->getRowArray();

            if ($row) {
                foreach ($fields as $field) {
                    if (!array_key_exists($field, $criteria) && array_key_exists($field, $row)) {
                        $criteria[$field] = $row[$field];
                    }
                }
            }
        }

        // Si aun faltan campos, no se puede validar
        if (count($criteria) < count($fields)) {
            return true;
        }

        // Construir query
        $builder = $db
            ->table($table)
            ->select('1')
            ->where($criteria)
            ->limit(1);

        // Excluir registro actual en updates
        if ($isUpdate) {
            $builder->where("{$pkField} !=", $item[$pkField]);
        }

        if ($builder->get()->getRow() !== null) {
            $this->error = lang('Validation.is_unique', ['field' => implode(', ', $fields)]);
            return false;
        }

        return true;
    }

    /**
     * Retorna el mensaje de error de la última validación.
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
