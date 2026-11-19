<?php

namespace App\Controllers\Traits;

use CodeIgniter\HTTP\Response;

/**
 * Proporciona métodos base para exportar datos a archivos (XLSX, CSV, PDF, etc.).
 * Los datos se sirven como descarga o visualización inline sin crear archivos internos.
 *
 * Uso típico:
 *   class MyController extends BaseController {
 *       use CrudTraits, ExportTraits;
 *
 *       public function export(): Response {
 *           $data = $this->baseRead(null, true)['data'] ?? [];
 *           return $this->exportToDownload($data, 'reporte.xlsx', [
 *               'title' => 'Reporte de Personas',
 *           ]);
 *       }
 *   }
 */
trait ExportTraits {
    use HookConfigTraits;

    private function __registerHookConfig_export(): void {
        $this->registerCallbacks(__TRAIT__, [
            'beforeExport',          // Modifica los datos antes de generar cualquier formato
            'resolveHeaderMap',      // Renombra las columnas: retorna [field => displayName]
            'rowFormatted',          // Formatea cada fila (afecta CSV, XLSX, ODS y PDF)
            'formatSpreadsheet',     // Personaliza PhpSpreadsheet antes de escribir datos

            // PDF
            'pdfConfig',             // Configura la instancia TCPDF (márgenes, marcas de agua, fuentes, etc.)
            'pdfHeader',             // Dibuja el encabezado; retorna false para saltar el header default
            'pdfTableRow',           // Estiliza cada fila de la tabla PDF (colores de fondo, tipografía)
            'pdfTableCell',          // Formatea el valor individual de cada celda en PDF
            'pdfFooterExtras',       // Contenido adicional después de la tabla PDF
            'renderPdf',             // By-pass total del renderizado PDF: recibe TCPDF y dibuja todo desde cero
        ]);

        $this->registerConfig(__TRAIT__, [
            // ─── Generales ───
            'exportHeaders'    => ['types' => ['array', 'NULL'], 'default' => null],
            // Columnas a exportar y su orden. null = todas las del primer registro.
            'sheetName'        => ['types' => ['string'], 'default' => 'Sheet1'],
            // Nombre de la hoja en XLSX/ODS.
            'delimiter'        => ['types' => ['string'], 'default' => ','],
            // Delimitador de columnas para CSV.
            'includeHeaders'   => ['types' => ['boolean'], 'default' => true],
            // Incluir fila de encabezados en la salida.
            'allowedFormats'   => ['types' => ['array'], 'default' => ['csv', 'xlsx', 'xls', 'ods', 'pdf']],
            // Formatos de exportación permitidos.
            'disposition'      => ['types' => ['string'], 'default' => 'attachment'],
            // 'attachment' para descarga, 'inline' para visualizar en el navegador.

            // ─── PDF ───
            'pdfOrientation'   => ['types' => ['string'], 'default' => 'L'],
            // Orientación: 'L' (landscape/apaisado) o 'P' (portrait/vertical).
            'pdfPageSize'      => ['types' => ['string'], 'default' => 'A4'],
            // Tamaño de página (A4, Letter, etc.).
            'pdfFont'          => ['types' => ['string'], 'default' => 'helvetica'],
            // Fuente del PDF (helvetica, courier, times o una TTF registrada vía TCPDF).
            'pdfFontSize'      => ['types' => ['string', 'int', 'integer'], 'default' => 8],
            // Tamaño base de fuente para los datos de la tabla.
            'pdfTimezone'      => ['types' => ['string', 'NULL'], 'default' => null],
            // Zona horaria para la fecha impresa. null = usa app_timezone().
            'pdfStyle'         => ['types' => ['array', 'NULL'], 'default' => null],
            // Estilos de tabla. Keys: border_width, border_color, header_bg,
            // header_text_color, even_row_bg, odd_row_bg, header_height, row_height.
        ]);
    }

    /**
     * Exporta datos a un archivo y lo envía como descarga o visualización inline.
     *
     * @param array  $data     Array de filas (ej: resultado de baseRead).
     * @param string $filename Nombre del archivo. La extensión define el formato.
     * @param array  $extra    Datos adicionales (title, subtitle, timezone, etc.).
     * @return Response        Descarga o visualización inline.
     *
     * Hook: beforeExport($data, $format, $extra) → modifica datos antes de generar
     * Hook: resolveHeaderMap($dataHeaders) → mapea [field => displayName]
     * Hook: rowFormatted($row, $index) → transforma cada fila
     */
    public function exportToDownload(array $data, string $filename, array $extra = []): Response {
        $format = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (empty($format)) {
            return $this->fail(lang('ExportTraits.format_required'), 400, 'EXPORT_FORMAT_REQUIRED');
        }

        $allowed = $this->getConfig('allowedFormats');
        if (!in_array($format, $allowed, true)) {
            return $this->fail(
                lang('ExportTraits.format_not_allowed', [$format, implode(', ', $allowed)]),
                400,
                'EXPORT_FORMAT_NOT_ALLOWED'
            );
        }

        // 1. beforeExport
        if ($this->hasCallback('beforeExport')) {
            $result = $this->runCallback('beforeExport', $data, $format, $extra);
            if ($result instanceof Response) {
                return $result;
            }
            if (is_array($result)) {
                $data = $result;
            }
        }

        // 2. Extraer headers del primer registro (o de exportHeaders si no hay datos)
        $dataHeaders = [];
        if (!empty($data)) {
            $firstRow = reset($data);
            if (is_array($firstRow)) {
                $dataHeaders = array_keys($firstRow);
            }
        } else {
            $configuredHeaders = $this->getConfig('exportHeaders');
            if (is_array($configuredHeaders)) {
                $dataHeaders = $configuredHeaders;
            }
        }

        // 3. Si exportHeaders está configurado, filtrar y ordenar
        $configuredHeaders = $this->getConfig('exportHeaders');
        if (is_array($configuredHeaders)) {
            $filtered = [];
            foreach ($configuredHeaders as $field) {
                if (in_array($field, $dataHeaders, true)) {
                    $filtered[] = $field;
                }
            }
            if (!empty($filtered)) {
                $dataHeaders = $filtered;
            }
        }

        // 4. Resolver display names
        $displayMap = $this->resolveHeaderDisplay($dataHeaders);

        // 5. Formatear filas
        $formattedData = [];
        foreach ($data as $i => $row) {
            if (!is_array($row)) {
                continue;
            }

            $newRow = [];
            foreach ($dataHeaders as $field) {
                $newRow[$displayMap[$field]] = $row[$field] ?? null;
            }

            if ($this->hasCallback('rowFormatted')) {
                $result = $this->runCallback('rowFormatted', $newRow, $i);
                if ($result instanceof Response) {
                    return $result;
                }
                if ($result === false) {
                    continue;
                }
                if (is_array($result)) {
                    $newRow = $result;
                }
            }

            $formattedData[] = $newRow;
        }

        // 6. Generar contenido según formato
        switch ($format) {
            case 'csv':
            case 'xlsx':
            case 'xls':
            case 'ods':
                return $this->generateSpreadsheet($format, $formattedData, $displayMap, $extra, $filename);

            case 'pdf':
                return $this->generatePdf($formattedData, $displayMap, $extra, $filename);

            default:
                return $this->fail(lang('ExportTraits.format_not_supported', [$format]), 400, 'EXPORT_FORMAT_NOT_SUPPORTED');
        }
    }

    // ----------------------------------------------------------------
    // Generadores por formato
    // ----------------------------------------------------------------

    /**
     * Genera un archivo XLSX/CSV/XLS/ODS y lo devuelve como Response.
     *
     * Hook: formatSpreadsheet($spreadsheet, $extra) → personaliza antes de escribir datos
     *
     * @param string $format     csv|xlsx|xls|ods
     * @param array  $data       Filas con valores formateados.
     * @param array  $displayMap Mapa [field => displayName].
     * @param array  $extra      Datos adicionales.
     * @param string $filename   Nombre del archivo.
     * @return Response
     */
    private function generateSpreadsheet(string $format, array $data, array $displayMap, array $extra, string $filename): Response {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle((string) $this->getConfig('sheetName'));

        if ($this->hasCallback('formatSpreadsheet')) {
            $result = $this->runCallback('formatSpreadsheet', $spreadsheet, $extra);
            if ($result instanceof Response) {
                ob_clean();
                return $result;
            }
        }

        $existingRow = $this->hasCallback('formatSpreadsheet') ? $sheet->getHighestDataRow() : 0;
        $startRow = $existingRow + 1;
        $col = 1;

        if ((bool) $this->getConfig('includeHeaders') && !empty($displayMap)) {
            foreach (array_values($displayMap) as $header) {
                $sheet->setCellValueExplicit(
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $startRow,
                    $header,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $col++;
            }
            $startRow++;
        }

        foreach ($data as $row) {
            $col = 1;
            foreach (array_values($row) as $value) {
                $sheet->setCellValueExplicit(
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $startRow,
                    $value ?? '',
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $col++;
            }
            $startRow++;
        }

        $writers = [
            'csv'  => \PhpOffice\PhpSpreadsheet\Writer\Csv::class,
            'xlsx' => \PhpOffice\PhpSpreadsheet\Writer\Xlsx::class,
            'xls'  => \PhpOffice\PhpSpreadsheet\Writer\Xls::class,
            'ods'  => \PhpOffice\PhpSpreadsheet\Writer\Ods::class,
        ];

        $writerClass = $writers[$format] ?? null;
        if (!$writerClass) {
            return $this->fail(lang('ExportTraits.format_not_supported', [$format]), 400, 'EXPORT_FORMAT_NOT_SUPPORTED');
        }

        $writer = new $writerClass($spreadsheet);

        if ($writer instanceof \PhpOffice\PhpSpreadsheet\Writer\Csv) {
            $writer->setDelimiter($this->getConfig('delimiter'));
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
        }

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        $mimes = [
            'csv'  => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        ];

        return $this->response
            ->setContentType($mimes[$format] ?? 'application/octet-stream')
            ->setHeader('Content-Disposition', $this->resolveDisposition() . '; filename="' . $filename . '"')
            ->setBody($content);
    }

    /**
     * Genera un PDF con tabla de datos y lo devuelve como Response.
     *
     * Hook: pdfConfig($pdf, $extra) → personaliza TCPDF (márgenes, marcas de agua, etc.)
     * Hook: renderPdf($pdf, $data, $headers, $extra, $filename) → by-pass total
     * Hook: pdfHeader($pdf, $title, $subtitle, $extra) → encabezado (false = salta default)
     * Hook: pdfFooterExtras($pdf, $extra) → contenido después de la tabla
     *
     * @param array  $data       Filas con valores formateados.
     * @param array  $displayMap Mapa [field => displayName].
     * @param array  $extra      Datos adicionales (title, subtitle, timezone).
     * @param string $filename   Nombre del archivo.
     * @return Response
     */
    private function generatePdf(array $data, array $displayMap, array $extra, string $filename): Response {
        $headers = array_values($displayMap);

        $pdf = new \TCPDF(
            $this->getConfig('pdfOrientation'),
            'mm',
            $this->getConfig('pdfPageSize'),
            true,
            'UTF-8',
            false
        );

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);

        if ($this->hasCallback('pdfConfig')) {
            $result = $this->runCallback('pdfConfig', $pdf, $extra);
            if ($result instanceof Response) {
                return $result;
            }
        }

        if ($this->hasCallback('renderPdf')) {
            $result = $this->runCallback('renderPdf', $pdf, $data, $headers, $extra, $filename);
            if ($result instanceof Response) {
                return $result;
            }
            if ($result !== true) {
                return $this->response
                    ->setContentType('application/pdf')
                    ->setHeader('Content-Disposition', $this->resolveDisposition() . '; filename="' . $filename . '"')
                    ->setBody($pdf->Output('', 'S'));
            }
        }

        $pdf->AddPage();

        $title = $extra['title'] ?? $filename;
        $subtitle = $extra['subtitle'] ?? null;

        $drawDefaultHeader = true;
        if ($this->hasCallback('pdfHeader')) {
            $result = $this->runCallback('pdfHeader', $pdf, $title, $subtitle, $extra);
            if ($result instanceof Response) {
                return $result;
            }
            $drawDefaultHeader = $result !== false;
        }

        if ($drawDefaultHeader) {
            $this->drawPdfHeader($pdf, $title, $subtitle);
        }

        $timezone = $extra['timezone'] ?? $this->getConfig('pdfTimezone') ?? app_timezone();
        $locale = service('request')->getLocale();
        $datePattern = $locale === 'es'
            ? "d 'de' MMMM 'del' yyyy, h:mm a"
            : 'MMMM d, yyyy, h:mm a';
        $dateFormatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $timezone,
            \IntlDateFormatter::GREGORIAN,
            $datePattern
        );
        $dateStr = $dateFormatter->format(new \DateTime());

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(136, 136, 136);
        $pdf->Cell(0, 6, lang('ExportTraits.generated_at', [$dateStr]), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(4);

        $abort = $this->drawPdfTable($pdf, $data, $headers);
        if ($abort instanceof Response) {
            return $abort;
        }

        if ($this->hasCallback('pdfFooterExtras')) {
            $result = $this->runCallback('pdfFooterExtras', $pdf, $extra);
            if ($result instanceof Response) {
                return $result;
            }
        }

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', $this->resolveDisposition() . '; filename="' . $filename . '"')
            ->setBody($pdf->Output('', 'S'));
    }

    /**
     * Dibuja el encabezado por defecto (título + subtítulo centrados).
     * Usa la fuente configurada en pdfFont.
     *
     * @param \TCPDF $pdf      Instancia de TCPDF.
     * @param string $title    Título del reporte.
     * @param string|null $subtitle Subtítulo opcional.
     */
    private function drawPdfHeader(\TCPDF $pdf, string $title, ?string $subtitle): void {
        $font = $this->getConfig('pdfFont');

        $pdf->SetFont($font, 'B', 18);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Cell(0, 10, $title, 0, 1, 'C');

        if ($subtitle !== null && $subtitle !== '') {
            $pdf->SetFont($font, '', 13);
            $pdf->SetTextColor(102, 102, 102);
            $pdf->Cell(0, 7, $subtitle, 0, 1, 'C');
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);
    }

    /**
     * Dibuja la tabla completa (header + filas de datos) en el PDF.
     * Lee estilos desde pdfStyle si están configurados.
     *
     * Hook: pdfTableRow($pdf, $row, $i, $headers) → estilo por fila (retorna false = skip)
     * Hook: pdfTableCell($value, $rowIdx, $colIdx, $header) → formatea valor de celda
     *
     * @param \TCPDF $pdf     Instancia de TCPDF.
     * @param array  $data    Filas con valores.
     * @param array  $headers Nombres de columnas.
     * @return Response|null Response si un hook aborta, null si todo ok.
     */
    private function drawPdfTable(\TCPDF $pdf, array $data, array $headers): ?Response {
        if (empty($headers)) {
            return null;
        }

        $font = $this->getConfig('pdfFont');
        $fontSize = (int) $this->getConfig('pdfFontSize');
        $showHeaders = (bool) $this->getConfig('includeHeaders');
        $style = $this->getConfig('pdfStyle') ?? [];

        $pageW = $pdf->getPageWidth();
        $margins = $pdf->getMargins();
        $availableW = $pageW - $margins['left'] - $margins['right'];
        $colW = $availableW / count($headers);

        $borderWidth = $style['border_width'] ?? 0.1;
        $borderColor = $style['border_color'] ?? [221, 221, 221];
        $borderStyle = [
            'T' => ['width' => $borderWidth, 'color' => $borderColor],
            'B' => ['width' => $borderWidth, 'color' => $borderColor],
            'L' => ['width' => $borderWidth, 'color' => $borderColor],
            'R' => ['width' => $borderWidth, 'color' => $borderColor],
        ];

        $headerHeight = $style['header_height'] ?? 8;
        $rowHeight = $style['row_height'] ?? 6;
        $headerBg = $style['header_bg'] ?? [240, 240, 240];
        $headerTextColor = $style['header_text_color'] ?? [0, 0, 0];
        $evenBg = $style['even_row_bg'] ?? [250, 250, 250];
        $oddBg = $style['odd_row_bg'] ?? [255, 255, 255];

        $cellPadding = 1;

        if (empty($data)) {
            $pdf->SetFont($font, '', $fontSize);
            $pdf->SetTextColor(153, 153, 153);
            $pdf->Cell($availableW, $rowHeight, lang('ExportTraits.no_records'), $borderStyle, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            return null;
        }

        if ($showHeaders) {
            $pdf->SetFont($font, 'B', $fontSize + 1);
            $pdf->SetFillColor($headerBg[0], $headerBg[1], $headerBg[2]);
            $pdf->SetTextColor($headerTextColor[0], $headerTextColor[1], $headerTextColor[2]);

            foreach ($headers as $header) {
                $pdf->Cell($colW, $headerHeight, $header, $borderStyle, 0, 'L', true);
            }
            $pdf->Ln();
        }

        $pdf->SetFont($font, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);

        foreach ($data as $i => $row) {
            if ($this->hasCallback('pdfTableRow')) {
                $result = $this->runCallback('pdfTableRow', $pdf, $row, $i, $headers);
                if ($result instanceof Response) {
                    return $result;
                }
                if ($result === false) {
                    continue;
                }
            }

            $pdf->SetFont($font, '', $fontSize);
            $pdf->SetTextColor(0, 0, 0);

            $rowValues = array_values($row);

            // 1. Aplicar hook y calcular alto de fila
            $maxLines = 1;
            foreach ($rowValues as $colIdx => $value) {
                $displayValue = (string) ($value ?? '');

                if ($this->hasCallback('pdfTableCell')) {
                    $cellResult = $this->runCallback(
                        'pdfTableCell',
                        $displayValue,
                        $i,
                        $colIdx,
                        $headers[$colIdx] ?? ''
                    );
                    if ($cellResult instanceof Response) {
                        return $cellResult;
                    }
                    if (is_string($cellResult)) {
                        $displayValue = $cellResult;
                    }
                }

                $rowValues[$colIdx] = $displayValue;

                $lines = $pdf->getNumLines($displayValue, $colW - ($cellPadding * 2));
                if ($lines > $maxLines) {
                    $maxLines = $lines;
                }
            }

            $rowH = max($rowHeight, $maxLines * $rowHeight);

            // 2. Salto de página si no cabe
            if ($pdf->getY() + $rowH > $pdf->getPageHeight() - $margins['bottom']) {
                $pdf->AddPage();

                if ($showHeaders) {
                    $pdf->SetFont($font, 'B', $fontSize + 1);
                    $pdf->SetFillColor($headerBg[0], $headerBg[1], $headerBg[2]);
                    $pdf->SetTextColor($headerTextColor[0], $headerTextColor[1], $headerTextColor[2]);

                    foreach ($headers as $header) {
                        $pdf->Cell($colW, $headerHeight, $header, $borderStyle, 0, 'L', true);
                    }
                    $pdf->Ln();
                }

                $pdf->SetFont($font, '', $fontSize);
                $pdf->SetTextColor(0, 0, 0);
            }

            // 3. Dibujar fondo de la fila
            $xStart = $pdf->GetX();
            $yStart = $pdf->GetY();

            if ($i % 2 === 1) {
                $pdf->SetFillColor($evenBg[0], $evenBg[1], $evenBg[2]);
            } else {
                $pdf->SetFillColor($oddBg[0], $oddBg[1], $oddBg[2]);
            }

            $pdf->Rect($xStart, $yStart, $availableW, $rowH, 'F');

            // 4. Dibujar cada celda con MultiCell (texto con wrap)
            foreach ($rowValues as $colIdx => $displayValue) {
                $x = $xStart + ($colIdx * $colW);
                $pdf->SetXY($x + $cellPadding, $yStart + $cellPadding);

                $pdf->SetFont($font, '', $fontSize);
                $pdf->SetTextColor(0, 0, 0);

                $pdf->MultiCell(
                    $colW - ($cellPadding * 2),
                    $rowHeight,
                    $displayValue,
                    0,
                    'L',
                    false,
                    0,
                    '',
                    '',
                    true,
                    0,
                    false,
                    true,
                    $rowH - ($cellPadding * 2),
                    'T'
                );
            }

            // 5. Dibujar bordes
            $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);
            $pdf->SetLineWidth($borderWidth);

            for ($c = 0; $c <= count($rowValues); $c++) {
                $x = $xStart + ($c * $colW);
                if ($c === 0 || $c === count($rowValues)) {
                    $pdf->Line($x, $yStart, $x, $yStart + $rowH);
                } else {
                    $pdf->Line($x, $yStart, $x, $yStart + $rowH);
                }
            }

            $pdf->Line($xStart, $yStart, $xStart + $availableW, $yStart);
            $pdf->Line($xStart, $yStart + $rowH, $xStart + $availableW, $yStart + $rowH);

            $pdf->SetXY($xStart, $yStart + $rowH);
        }

        return null;
    }

    // ----------------------------------------------------------------
    // Resolución de headers
    // ----------------------------------------------------------------

    /**
     * Resuelve la disposición de la respuesta (attachment o inline).
     * Si el valor no es válido, usa 'attachment' como fallback.
     *
     * @return string 'attachment' | 'inline'
     */
    private function resolveDisposition(): string {
        $disposition = $this->getConfig('disposition');
        if (!in_array($disposition, ['inline', 'attachment'], true)) {
            $disposition = 'attachment';
        }
        return $disposition;
    }

    /**
     * Resuelve los nombres de visualización de las columnas.
     * Aplica el hook resolveHeaderMap si está definido.
     *
     * Hook: resolveHeaderMap($dataHeaders) → retorna [field => displayName]
     *
     * @param array $dataHeaders Nombres originales de las columnas.
     * @return array Mapa [field => displayName].
     */
    private function resolveHeaderDisplay(array $dataHeaders): array {
        $map = [];
        if ($this->hasCallback('resolveHeaderMap')) {
            $result = $this->runCallback('resolveHeaderMap', $dataHeaders);
            if (is_array($result)) {
                foreach ($result as $field => $display) {
                    if (in_array($field, $dataHeaders, true)) {
                        $map[$field] = $display;
                    }
                }
            }
        }

        $displayMap = [];
        foreach ($dataHeaders as $field) {
            $displayMap[$field] = (string) ($map[$field] ?? $field);
        }

        return $displayMap;
    }
}
