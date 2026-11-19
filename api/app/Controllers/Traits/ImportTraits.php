<?php

namespace App\Controllers\Traits;

use CodeIgniter\HTTP\Response;

/**
 * Proporciona métodos base para importar datos desde archivos (CSV, XLSX, XLS, ODS).
 * Utiliza PhpSpreadsheet como motor único de parseo.
 *
 * Uso típico:
 *   class MyController extends BaseController {
 *       use CrudTraits, ImportTraits;
 *
 *       public function upload(): Response {
 *           $this->setConfig('expected_headers', ['name', 'email']);
 *           $result = $this->importFromUpload('file');
 *           if ($result instanceof Response) return $result;
 *           return $this->baseCreate($result);
 *       }
 *   }
 */
trait ImportTraits {
    use HookConfigTraits;

    private function __registerHookConfig_import(): void {
        $this->registerCallbacks(__TRAIT__, [
            'beforeParse',
            'resolveHeaderMap',
            'rowParsed',
            'afterParse',
        ]);

        $this->registerConfig(__TRAIT__, [
            'expectedHeaders'   => ['types' => ['array', 'NULL'], 'default' => null],
            'skipRows'          => ['types' => ['integer'], 'default' => 0],
            'headerRow'         => ['types' => ['integer'], 'default' => 0],
            'delimiter'         => ['types' => ['string'], 'default' => ','],
            'encoding'          => ['types' => ['string'], 'default' => 'UTF-8'],
            'trimValues'        => ['types' => ['boolean'], 'default' => true],
            'emptyToNull'       => ['types' => ['boolean'], 'default' => false],
            'allowedExtensions' => ['types' => ['array'], 'default' => ['csv', 'xlsx', 'xls', 'ods']],
        ]);
    }

    /**
     * Importa datos desde un archivo local.
     *
     * @param string $path Ruta absoluta al archivo.
     * @return array|Response Array de filas, o Response con error.
     */
    public function importFromFile(string $path): array|Response {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // 1. beforeParse
        if ($this->hasCallback('beforeParse')) {
            $result = $this->runCallback('beforeParse', $path, $ext);
            if ($result instanceof Response) {
                return $result;
            }
        }

        // 2. Validar extensión
        $allowed = $this->getConfig('allowedExtensions');
        if (!in_array($ext, $allowed, true)) {
            return $this->fail(
                lang('ImportTraits.extension_not_allowed', [$ext, implode(', ', $allowed)]),
                400,
                'IMPORT_EXTENSION_NOT_ALLOWED'
            );
        }

        // 3. Validar que el archivo exista
        if (!file_exists($path) || !is_file($path)) {
            return $this->fail(
                lang('ImportTraits.file_not_found', [$path]),
                404,
                'IMPORT_FILE_NOT_FOUND'
            );
        }

        // 4. Parsear con PhpSpreadsheet
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Throwable $e) {
            log_message('error', "[IMPORT_PARSE_ERROR] {exception}\nFile:\t{path}\nStack trace:\n{stackTrace}", [
                'exception' => $e,
                'path'      => $path,
                'stackTrace'=> $e->getTraceAsString(),
            ]);
            return $this->fail(
                lang('ImportTraits.parse_error', [$e->getMessage()]),
                500,
                'IMPORT_PARSE_ERROR'
            );
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true);

        // 5. Extraer headers
        $headerIndex = (int) $this->getConfig('headerRow');
        $skipRows    = (int) $this->getConfig('skipRows');

        if (!isset($rows[$headerIndex]) || !is_array($rows[$headerIndex])) {
            return $this->fail(
                lang('ImportTraits.headers_not_found'),
                400,
                'IMPORT_HEADERS_NOT_FOUND'
            );
        }

        $fileHeaders = array_values(array_map('strval', $rows[$headerIndex]));

        // Headers vacíos o sin contenido real
        $filteredHeaders = array_filter(array_map('trim', $fileHeaders));
        if (empty($filteredHeaders)) {
            return $this->fail(
                lang('ImportTraits.headers_not_found'),
                400,
                'IMPORT_HEADERS_NOT_FOUND'
            );
        }

        // Filas de datos después del header
        $startRow = max($headerIndex, $skipRows) + 1;
        $dataRows = array_slice($rows, $startRow);

        // 6. Resolver mapa de columnas
        $mappingResult = $this->resolveHeaderMapping($fileHeaders);
        if ($mappingResult instanceof Response) {
            return $mappingResult;
        }
        $mapping = $mappingResult;

        // 7. Procesar filas
        $data = [];
        foreach ($dataRows as $i => $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }

            $rawRow = array_values($rawRow);
            $row = $this->buildRow($rawRow, $fileHeaders, $mapping);

            if ($this->hasCallback('rowParsed')) {
                $result = $this->runCallback('rowParsed', $row, $i);
                if ($result instanceof Response) {
                    return $result;
                }
                if ($result === false) {
                    continue;
                }
                if (is_array($result)) {
                    $row = $result;
                }
            }

            $data[] = $row;
        }

        // 8. afterParse
        if ($this->hasCallback('afterParse')) {
            $result = $this->runCallback('afterParse', $data);
            if ($result instanceof Response) {
                return $result;
            }
            if (is_array($result)) {
                $data = $result;
            }
        }

        return $data;
    }

    /**
     * Importa datos desde un archivo subido vía HTTP.
     *
     * @param string $fieldName Nombre del campo file en el formulario.
     * @return array|Response Array de filas, o Response con error.
     */
    public function importFromUpload(string $fieldName): array|Response {
        $file = $this->request->getFile($fieldName);

        if ($file === null || !$file->isValid()) {
            $msg = $file !== null ? $file->getErrorString() : 'No se recibió ningún archivo.';
            return $this->fail(
                lang('ImportTraits.upload_invalid', [$msg]),
                400,
                'IMPORT_UPLOAD_ERROR'
            );
        }

        // Copiar a ruta temporal con extensión original
        $ext = strtolower($file->getExtension());
        $destPath = $file->getTempName() . ($ext ? '.' . $ext : '');
        if ($ext && $file->getTempName() !== $destPath) {
            copy($file->getTempName(), $destPath);
            return $this->importFromFile($destPath);
        }

        return $this->importFromFile($file->getTempName());
    }

    // ----------------------------------------------------------------
    // Métodos internos
    // ----------------------------------------------------------------

    /**
     * Resuelve el mapeo entre expectedHeaders y los headers reales del archivo.
     *
     * @param string[] $fileHeaders Headers extraidos del archivo.
     * @return array|Response [expected => file_header_name|null] o Response.
     */
    private function resolveHeaderMapping(array $fileHeaders): array|Response {
        $expected = $this->getConfig('expectedHeaders') ?? $fileHeaders;

        $map = [];
        if ($this->hasCallback('resolveHeaderMap')) {
            $result = $this->runCallback('resolveHeaderMap', $fileHeaders);
            if ($result instanceof Response) {
                return $result;
            }
            $map = is_array($result) ? $result : [];
        }

        $mapping = [];
        foreach ($expected as $field) {
            if (isset($map[$field])) {
                $mapping[$field] = $map[$field];
            } elseif (in_array($field, $fileHeaders, true)) {
                $mapping[$field] = $field;
            } else {
                $mapping[$field] = null;
            }
        }

        return $mapping;
    }

    /**
     * Construye una fila aplicando el mapeo de columnas y las opciones de formato.
     */
    private function buildRow(array $rawRow, array $fileHeaders, array $mapping): array {
        $trim       = (bool) $this->getConfig('trimValues');
        $emptyToNull = (bool) $this->getConfig('emptyToNull');

        $row = [];
        foreach ($mapping as $field => $colName) {
            $value = null;
            if ($colName !== null) {
                $idx = array_search($colName, $fileHeaders, true);
                $value = $idx !== false ? ($rawRow[$idx] ?? null) : null;
            }

            if (is_string($value)) {
                if ($trim) {
                    $value = trim($value);
                }
                if ($emptyToNull && $value === '') {
                    $value = null;
                }
            }

            $row[$field] = $value;
        }

        return $row;
    }
}
