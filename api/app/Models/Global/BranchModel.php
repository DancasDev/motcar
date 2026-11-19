<?php

namespace App\Models\Global;

use App\Models\BaseModel;

/**
 * Sucursal de la empresa (árbol jerárquico autorreferente).
 *
 * - `parent_id` NULL = raíz; puede apuntar a cualquier otra sucursal.
 * - La unicidad de `name` es por rama (parent_id): dos hijas del mismo
 *   padre no pueden llamarse igual.
 *
 * NOTA sobre la recursividad: `getBranchesMap()` e `isInAncestors()`
 * recorren el árbol con CTEs recursivas (`WITH RECURSIVE`) y no pueden caer
 * en un bucle infinito:
 *  - En datos válidos la recursión se agota sola: hay un número finito de
 *    nodos y una rama termina al llegar a un nodo sin padre (`parent_id IS
 *    NULL` no matchea el UNION ALL).
 *  - Aunque hubiera datos corruptos con un ciclo (un padre apuntando a un
 *    descendiente), cada recursión guarda el camino recorrido y se corta en
 *    cuanto un nodo se repite (`NOT FIND_IN_SET(...)`); además hay un tope
 *    explícito de profundidad (`depth < 1000`).
 */
class BranchModel extends BaseModel {
    protected $table = 'glb_branch';

    private const BRANCH_MAP_CACHE_KEY = 'db_brn_map';
    private const BRANCH_MAP_CACHE_TTL = 3600;

    protected $allowedFields = [
        'parent_id', 'name', 'is_disabled',
    ];

    protected $validationRules = [
        'parent_id' => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,deleted_at:IS_NULL;is_disabled:0]',
        'name'      => 'required|string|max_length[30]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Mapa de sucursales (cacheado): `[branch_id] => [...]` con el breadcrumb completo
     * y el estado propio y de la rama.
     * En miss o $reset = true, ejecuta la consulta recursiva CTE y almacena en caché.
     * Si no hay registros ([]), se almacena igualmente para evitar consultas recurrentes (anti-stampede).
     */
    public function getMapByCache(bool $reset = false): array {
        $cache = service('cache');
        $cacheKey = self::BRANCH_MAP_CACHE_KEY;

        if ($reset) {
            $cache->delete($cacheKey);
        }

        $map = $cache->get($cacheKey);
        if (!is_array($map)) {
            $sql = "
                WITH RECURSIVE branch_path AS (
                    SELECT id, parent_id, name, is_disabled, deleted_at,
                           CAST(id AS CHAR(255)) AS scope_path,
                           CAST(name AS CHAR(255)) AS name_path,
                           0 AS depth,
                           0 AS inherited_disabled,
                           0 AS inherited_deleted,
                           CAST(id AS CHAR(255)) AS visit_path
                    FROM {$this->table}
                    WHERE parent_id IS NULL
                    UNION ALL
                    SELECT b.id, b.parent_id, b.name, b.is_disabled, b.deleted_at,
                           CONCAT(bp.scope_path, '/', b.id),
                           CONCAT(bp.name_path, ' / ', b.name),
                           bp.depth + 1,
                           (bp.inherited_disabled OR bp.is_disabled = '1') AS inherited_disabled,
                           (bp.inherited_deleted OR bp.deleted_at IS NOT NULL) AS inherited_deleted,
                           CONCAT(bp.visit_path, ',', b.id)
                    FROM {$this->table} b
                    INNER JOIN branch_path bp ON b.parent_id = bp.id
                    WHERE bp.depth < 1000
                      AND NOT FIND_IN_SET(b.id, bp.visit_path)
                )
                SELECT bp.id, bp.parent_id, bp.scope_path, bp.name_path, bp.depth,
                       bp.is_disabled, bp.deleted_at, bp.inherited_disabled, bp.inherited_deleted,
                       (SELECT COUNT(*) FROM {$this->table} c WHERE c.parent_id = bp.id AND c.deleted_at IS NULL) AS children_count_all,
                       (SELECT COUNT(*) FROM {$this->table} c WHERE c.parent_id = bp.id
                           AND c.is_disabled = '0' AND c.deleted_at IS NULL) AS children_count_available
                FROM branch_path bp
                ORDER BY bp.depth, bp.id
            ";

            $results = $this->db->query($sql)->getResultArray();

            $map = [];
            foreach ($results as $row) {
                $map[(int) $row['id']] = [
                    'parent_id'                  => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                    'scope'                      => $row['scope_path'],
                    'name'                       => $row['name_path'],
                    'depth'                      => (int) $row['depth'],
                    'is_disabled'                => (bool) $row['is_disabled'],
                    'deleted_at'                 => $row['deleted_at'],
                    'children_count_all'         => (int) $row['children_count_all'],
                    'children_count_available'   => (int) $row['children_count_available'],
                    'is_inherited_disabled'      => (bool) $row['inherited_disabled'],
                    'is_inherited_deleted'       => (bool) $row['inherited_deleted'],
                ];
            }

            $cache->save($cacheKey, $map, self::BRANCH_MAP_CACHE_TTL);
        }

        return $map;
    }

    /**
     * Invalida la caché del mapa. Llamar tras cualquier cambio de sucursal
     * (crear, actualizar/mover/inhabilitar, eliminar o restaurar).
     */
    public function removeMapByCache(): void {
        service('cache')->delete(self::BRANCH_MAP_CACHE_KEY);
    }

    public function getByCache(bool $reset = false): array {
        return $this->getMapByCache($reset);
    }

    public function removeByCache(): void {
        $this->removeMapByCache();
    }

    /**
     * Sube por la cadena de padres de `$branchId` y devuelve true si
     * `$candidateId` está en ella (es decir, `$candidateId` es ancestro de
     * `$branchId`). Se usa para validar un ciclo al fijar un nuevo `parent_id`:
     * hay ciclo si el nuevo padre es descendiente del nodo, o sea
     * `isInAncestors(nuevoPadre, nodo)`.
     */
    public function isInAncestors(int $branchId, int $candidateId): bool {
        if ($branchId === $candidateId) {
            return true;
        }

        $sql = "
            WITH RECURSIVE branch_ancestors AS (
                SELECT id, parent_id, CAST(id AS CHAR(255)) AS path, 0 AS depth
                FROM {$this->table} WHERE id = ?
                UNION ALL
                SELECT p.id, p.parent_id, CONCAT(ba.path, ',', p.id), ba.depth + 1
                FROM {$this->table} p
                INNER JOIN branch_ancestors ba ON p.id = ba.parent_id
                WHERE ba.depth < 1000
                  AND NOT FIND_IN_SET(p.id, ba.path)
            )
            SELECT 1 FROM branch_ancestors WHERE parent_id = ? LIMIT 1
        ";

        return $this->db->query($sql, [$branchId, $candidateId])->getRow() !== null;
    }
}
